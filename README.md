dirty-slsapp-api
================

dirty scraping lib for springloops since they don't provide an api yet (20120910).
no license, just use at your own risk.

note: this is not in any way endorsed or supported by springloops inc.

usage as standalone php class:  
$slsapp = new Slsapp;  
$response = $slsapp->get_milestones();

usage from codeigniter:  
$this->load->library('slsapp');  
this->slsapp->get_milestones();

known issues:
- class does not report back if a milestone is late, upcoming or done.
- cannot yet get tickets by project id, but should be straight forward to implement if needed.
- error handling when login fails is... not excellent.
