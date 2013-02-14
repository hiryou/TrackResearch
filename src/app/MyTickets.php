<?php

require_once(LIB_PATH.'/Curl/CurlBot.php');

require_once(LIB_PATH.'/Function/String.php'); 
require_once(LIB_PATH.'/Function/System.php');

final class MyTickets {
    
    private static $_homeUrl = 'http://track.research.pdx.edu/my';
    private static $_baseUrl = 'http://track.research.pdx.edu/my?page=[$page]&order=created&sort=desc&tid_1[]=56&tid_1[]=73&tid_1[]=50&tid_1[]=75&tid_1[]=46&tid_1[]=76&tid_1[]=45&tid_1[]=74&tid_1[]=47&tid_1[]=49&tid_1[]=48';
    private static $_groupIdArg = 'group_nid[]';
    
    private $_odinBot = null;
    
    /**
     * $_projectIds is indexed by project name and value being project ID
     */
    private $_projectIds = Array();
    private $_projectsName = '';
    
    private $_dateFrom  = '';
    private $_dateTo    = '';
    
    public function __construct(Curl_CurlBot $odinBot) {
        $this->_odinBot = $odinBot;
        $this->_projectIds = Array();
        $this->_projectNames = '';
        
        $this->_dateFrom  = '';
        $this->_dateTo    = '';
    }
    
    public function setProjectNames($commaSeparatedProjName) {
        $this->_projectNames = $commaSeparatedProjName;
    }
    
    public function setDateFrom($date) {
        $this->_dateFrom = $date;
    }
    
    public function setDateTo($date) {
        $this->_dateTo = $date;
    }
    
    public function fetchNext() {
        static $totPage = -1;
        static $page = 0;
        
        while ($page<$totPage || $totPage==-1) {
            // Construct the url to the current page of my tickets list
            $url = str_replace('[$page]', $page, self::$_baseUrl);
            // and go to this page
            $this->msg('Fetching page ' . ($page+1) . '...');
            $this->_odinBot->navigateTo($url);
            $html = $this->_odinBot->getPageBody();
            $this->msg('-- Finished');
            
            // if this is the first page, attempt to get the number of total pages
            if ($totPage==-1) {
                $totPage = $this->_getTotalPage($html);
                $this->msg('-- Total pages: ' . $totPage);
            }
            
            // collect tickets statistics on this page
            $this->msg('-- Collecting tickets - Page '.($page+1).'/'.$totPage.': ');
            $this->msg('--------------------------------------------');
            list($tickets, $totBill, $totNonBill) = $this->_getTickets($html);
            $this->msg('');
            $this->msg('Total Billable hours = ' . $totBill);
            $this->msg('Total Non-billable hours = ' . $totNonBill);
            $this->msg('');
            
            // write this statistics to file
            $fileName = 'All_'.$this->_dateFrom.'_'.$this->_dateTo.'_page_'.($page+1).'.txt';
            $this->_writeStatToFile($fileName, $tickets, $totBill, $totNonBill);
            $this->msg('-- Content written to file '.$fileName);
            $this->msg('');
            
            // prepare for next page
            ++$page;
            // true indicates that we still have more pages to fetch next
            return true;
        }
        
        // false means we finished fetching all pages
        return false;
    }
    
    /**
     * 
     */
    private function _getTotalPage($html) {
        // Locate the pagination menu
        $pageBlock = Function_String::getHtmlElements(
            $html, 
            array(
                array('ul', 'class', 'pager-list', 0)
            )
        ); 
        $pageBlock = $pageBlock[0];
        
        // then, see how many pages does it have
        $pages = Function_String::getHtmlChildElementsByAttributes(
            $pageBlock, 'li', array() 
        );
        return count($pages);
    }
    
    private function _getTickets($html) {
        // Set the default timezone to use. Available as of PHP 5.1
        date_default_timezone_set('UTC');
        
        // Locate the main div containing all tickets
        $mainDiv = Function_String::getHtmlElements(
            $html,
            array(
                array('div', 'class', 'content-wrapper', 0)
            )
        );
        $mainDiv = $mainDiv[0];
        // get the tbody grid containing tickets
        $grid = Function_String::locateHtmlElementByChildsOrder($mainDiv, array(1, 2, 2, 2));
        // get array of tickets as plain html
        $plainTickets = Function_String::getHtmlChildElementsByAttributes($grid, 'tr');
        //echo count($plainTickets); die;
        
        // process to read ticket details
        $tickets = array();
        for ($i=0; $i<count($plainTickets); $i++) {
            $elements = Function_String::getHtmlChildElementsByAttributes($plainTickets[$i], 'td');
            $ticket = new stdClass();
            $ticket->id     = trim($elements[0]->nodeValue);
            $ticket->url    = 'http://track.research.pdx.edu/project/otrec/ticket/' . $ticket->id;
            $ticket->name   = trim($elements[1]->nodeValue);
            $ticket->priority   = trim($elements[3]->nodeValue);
            $ticket->status     = trim($elements[4]->nodeValue);
            $date = trim($elements[5]->nodeValue); $els = explode('/', $date);
            $ticket->created    = date('Y-m-d', mktime(0, 0, 0, $els[0], $els[1], $els[2]));
            $tickets[] = $ticket;
        }
        //print_r($tickets); die;
        
        // period of tracking hours
        $startDate  = $this->_dateFrom; $endDate = $this->_dateTo;
        $totalBillableHours = 0;
        $totalNonBillableHours = 0;
        $result = Array();
        
        // for each ticket, retrieve the hours within the given tracking period
        for ($i=0; $i<count($tickets); $i++) {
            $this->_odinBot->navigateTo($tickets[$i]->url);
            $html = $this->_odinBot->getPageBody();
            $hours = $this->_extractTicketHours($html, $startDate, $endDate);
            $tickets[$i]->billable_hours = $hours['billable_hours'];
            $tickets[$i]->non_billable_hours = $hours['non_billable_hours'];
            $tickets[$i]->report 
                = ($tickets[$i]->billable_hours+$tickets[$i]->non_billable_hours) > 0;
            
            if ($tickets[$i]->report) {
                $totalBillableHours += $tickets[$i]->billable_hours;
                $totalNonBillableHours += $tickets[$i]->non_billable_hours;
                $this->msg(
                    '-- Ticket #'.$tickets[$i]->id.': ' . $tickets[$i]->name . "\n" .
                    '---- Billable hours = ' . $tickets[$i]->billable_hours . "\n" .
                    '---- Non-billable hours = ' . $tickets[$i]->non_billable_hours
                );
                $result[] = $tickets[$i];
            }
        }
        
        return array($result, $totalBillableHours, $totalNonBillableHours);
    }

    private function _writeStatToFile($fileName, $tickets, $totBill, $totNonBill) {
        $content = '';
        foreach ($tickets as $t) {
            $content .= '#'.$t->id.': '.$t->name . "\n";
            $content .= 'Billable hours = ' . $t->billable_hours . "\n";
            $content .= 'Non-billable hours = ' . $t->non_billable_hours . "\n";
            $content .= "------------------------------------------------------------\n\n";
        }
        $content .= 'Total Billable hours = '.$totBill . "\n";
        $content .= 'Total Non-billable hours = '.$totNonBill . "\n";
        Function_System::writeToFile($fileName, $content);
    }

    /**
     * Print a message on console, used for printing on going progress
     * @param String $msg
     */
    public function msg($msg) {
        print $msg;
        print "\n";
    }

    /**
     * Enter description here...
     *
     * @param unknown_type $ticketHtml
     * @param unknown_type $startDate
     * @param unknown_type $endDate
     * @return unknown
     */
    private function _extractTicketHours($ticketHtml, $startDate, $endDate) {
        // retrieve all the time entry logs
        $timeEntries = Function_String::getHtmlElements(
            $ticketHtml,
            array(
                array('table', 'class', 'time_tracker_entry_comment', 1),
                array('tbody'), array('tr'), array('td')
            )
        );
        
        // prepare billable and non-billable hours
        $billHours      = 0;
        $nonBillHours   = 0;
        
        // for each entry to do the math addition 
        if (count($timeEntries)) {
            foreach ($timeEntries as $entry) {
                // prepare time in hours
                $hours = 0;
                // this is the whole text containing everything
                $text = strtolower($entry->nodeValue);
                // extract the time portion
                if (substr_count($text, '('))
                    $time = Function_String::findNext(0, array('time:', '('), 0, false, false, $text, $temp);
                else
                    $time = Function_String::findNext(0, array('time:', 'on'), 0, false, false, $text, $temp);
                $els = explode(',', $time);
                foreach ($els as $el) {
                    $temp = explode(' ', trim($el));
                    $num = trim($temp[0]);
                    $unit = trim($temp[1]);
                    if (substr_count($unit, 'hour'))
                        $hours += $num;
                    else if (substr_count($unit, 'min'))
                        $hours += $num/60;
                }
                $els = Function_String::findNext(0, array('on', 'billable'), 0, false, false, $text, $temp);
                $els = explode('/', $els);
                $date = date('Y-m-d', mktime(0, 0, 0, $els[0], $els[1], $els[2]));
                $billable   = Function_String::findNext(0, array('billable:', '.'), 0, false, false, $text, $temp);
                
                // remember: only extract hours within the given range
                if ($startDate <= $date && $date <= $endDate) {
                    if ($billable == 'yes')
                        $billHours += $hours;
                    else if ($billable == 'no')
                        $nonBillHours += $hours;
                }
            }
        }
        
        // return
        return array('billable_hours' => $billHours, 'non_billable_hours' => $nonBillHours);
    }
    
    /**
     * Invoke this function to simply test the home page html response
     */
    public function getProjectIds() {
        if (!count($this->_projectIds)) {
            $this->_fetchProjectIds();
        }
        //print_r($this->_projectIds);
    }
    
    private function _fetchProjectIds() {
        // Navigate to home page
        $this->_odinBot->navigateTo(self::$_homeUrl);
        $html = $this->_odinBot->getPageBody();
        
        // locate the projecst combo selection
        $projectsCombo = Function_String::getHtmlElements(
            $html, 
            array(
                array('select', 'name', 'group_nid[]', 1)
            )
        ); 
        $projectsCombo = $projectsCombo[0];
        
        // then, locate the list of projects
        $projects = Function_String::getHtmlChildElementsByAttributes(
            $projectsCombo, 'option', array() 
        ); 
        
        foreach ($projects as $p) {
            $id     = trim($p->getAttribute('value'));
            $name   = trim($p->nodeValue);
            $this->_projectIds[$name] = $id;
        } 
    }
    
}


