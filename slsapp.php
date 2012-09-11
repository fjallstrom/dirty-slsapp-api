<?php
#error_reporting(E_ALL);

# dirty scraping lib for springloops since they don't provide an api yet (20120910).
# no license, just use at your own risk.
# by peder@earthpeople.se
# note: this is not in any way endorsed or supported by springloops inc.

# usage as standalone php class:
# $slsapp = new Slsapp;
# $milestones = $slsapp->get_milestones();
# $tickets = $slsapp->get_tickets();

# usage from codeigniter:
# $this->load->library('slsapp');
# $milestones = this->slsapp->get_milestones();
# $tickets = this->slsapp->get_tickets();

# known issues:
# - get_milestones does not report back if a milestone is late, upcoming or done.
# - error handling when login fails is... not excellent.
# - to use get_tickets, you may need to change 5051 to the integer in the location field of your browser when visiting the your tickets page in slsapp

class Slsapp {

	private static $base_url = 'https://xxx.slsapp.com/';
	private static $username = 'xxx';
	private static $password = 'xxx';
	private static $tmpdir = '/tmp/';

	function __construct(){
		$this->attempts = 0;
		$this->max_attempts = 2;
	}
	
	private function _login(){
		if($this->attempts >= $this->max_attempts){
			die('login to slsapp failed after '.$this->attempts.' attempts');
		}
		$method = 'login';
		$ch = curl_init();
		$options = array(
			CURLOPT_URL => $this::$base_url.$method,
			CURLOPT_POSTFIELDS => "login=".$this::$username."&password=".$this::$password,
			CURLOPT_POST => 1,
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_CONNECTTIMEOUT => 10,
			#CURLOPT_VERBOSE => true,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_COOKIEJAR => $this::$tmpdir.'slsapp.cookie',
			CURLOPT_COOKIEFILE => $this::$tmpdir.'slsapp.cookie',
			CURLOPT_SSL_VERIFYPEER => false,
		);
		curl_setopt_array($ch, $options);
		$data = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		$this->attempts++;
		sleep(2);
	}
	
	function get_milestones(){
		$method = 'milestones';
		$ttl = 180;
		if($response = $this->_request($this::$base_url.$method, $ttl)){
			$lines = explode("\n", $response);
			if($lines){
				for($i=0;$i<count($lines);$i++){
					if(stristr($lines[$i], '<div class="milestoneBox')){
						for($j=0;$j<30;$j++){
							$line = trim($lines[$i+$j]);
							
							# get project_id + milestone name + milestone id
							preg_match_all('!<a href="\/project\/(.*?)\/milestone/(.*?)">(.*?)<\/a>!', $line, $match);
							if(!empty($match[1][0]) && !empty($match[2][0]) && !empty($match[3][0])){
								$tmp['project_id'] = (int)$match[1][0];
								$tmp['milestone_id'] = (int)$match[2][0];
								$tmp['milestone'] = (string)$match[3][0];
							}
							unset($match);
					
							# get responsible user
							preg_match_all('!<span class="fl"><a rel="(.*?)" class="AssignedUser" href="(.*?)">(.*?)<\/a> is responsible<\/span>!', $line, $match);
							if(!empty($match[3][0])){
								$tmp['responsible'] = (string)$match[3][0];
							}
							unset($match);
							
							# get due date
							preg_match_all('!<input class="milestoneInputDueDate" type="hidden" value="(.*?)" \/>!', $line, $match);
							if(!empty($match[1][0])){
								$tmp['due'] = (int)$match[1][0];
							}
							unset($match);
							
							# get open / closed tickets
							preg_match_all('!">(.*?) open &amp; (.*?) closed<\/a>!', $line, $match);
							if(isset($match[1][0]) && isset($match[2][0])){
								$tmp['tickets_open'] = (int)$match[1][0];
								$tmp['tickets_closed'] = (int)$match[2][0];
							}
							unset($match);
						}
						
						# prepare for return
						$milestone[$tmp['project_id']]["milestones"][$tmp['milestone_id']] = $tmp;
						$milestone[$tmp['project_id']]["milestones"][$tmp['milestone_id']]['url'] = $this::$base_url."project/".$tmp['project_id']."/milestone/".$tmp['milestone_id'];
						$milestone[$tmp['project_id']]["url"] = $this::$base_url."project/".$tmp['project_id'];
						unset($tmp);
					}
				}
			}
			return $milestone;
		}else{
			$this->_login();
			return $this->get_milestones();
		}	
	}
	
	public function get_tickets($project_id = null, $milestone_id = null){
		$method = 'tickets/5051?format=json';
		$ticket = array();
		$ttl = 180;
		if($response = $this->_request($this::$base_url.$method, $ttl)){
			if($response = @json_decode($response)){
				foreach($response->tickets as $item){
					$ticket[$item->projectId][$item->id]->date->created = $item->created;
					$ticket[$item->projectId][$item->id]->date->updated = $item->updated;
					$ticket[$item->projectId][$item->id]->date->due = $item->dueDate;
					$ticket[$item->projectId][$item->id]->assignment = $item->assignment;
					$ticket[$item->projectId][$item->id]->title = $item->title;
					$ticket[$item->projectId][$item->id]->description = $item->desc;
					$ticket[$item->projectId][$item->id]->priority = $item->priority->name;
					$ticket[$item->projectId][$item->id]->status = $item->status->name;
					$ticket[$item->projectId][$item->id]->milestone = $item->milestone->id;
				}
				return $ticket;
			}
		}else{
			$this->_login();
			return $this->get_tickets();
		}
	}
	
	private function _request($url, $ttl = 86400, $force_new = 0) {
		$cachefile = $this::$tmpdir.'slsapp_'.md5($url).'.cache';
		$filemtime = @filemtime($cachefile); 
		if (!$filemtime || (time() - $filemtime >= $ttl || $force_new)){
			$ch = curl_init();
			$options = array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_CONNECTTIMEOUT => 10,
				#CURLOPT_VERBOSE => true,
				CURLOPT_TIMEOUT => 10,
				CURLOPT_COOKIEJAR => $this::$tmpdir.'slsapp.cookie',
				CURLOPT_COOKIEFILE => $this::$tmpdir.'slsapp.cookie',
				CURLOPT_SSL_VERIFYPEER => false,
			);
			curl_setopt_array($ch, $options);
			$data = curl_exec($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			if($http_code === 200){
				if(stristr($data, 'sign in</title>')){
					return false;
				}else{
					@file_put_contents($cachefile, $data);
					return $data;
				}
			} else {
				return false;
			}
		}else{
			$result = @file_get_contents($cachefile);
			return $result;
		}
	}

}