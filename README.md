dirty-slsapp-api
================

dirty scraping lib for springloops since they don't provide an api yet (20120910).
no license, just use at your own risk.

note: this is not in any way endorsed or supported by springloops inc.

usage as standalone php class:  
$slsapp = new Slsapp;  
$milestones = $slsapp->get_milestones();
$tickets = $slsapp->get_tickets();

usage from codeigniter:  
$this->load->library('slsapp');  
$milestones = $this->slsapp->get_milestones();
$tickets = $this->slsapp->get_tickets();

known issues:
- class does not report back if a milestone is late, upcoming or done.
- error handling when login fails is... not excellent.
- to use get_tickets, you may need to change 5051 to the integer in the location field of your browser when visiting the your tickets page in slsapp
