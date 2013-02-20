<?php
namespace NethServer\Module\Dashboard\SystemStatus;

/*
 * Copyright (C) 2013 Nethesis S.r.l.
 *
 * This script is part of NethServer.
 *
 * NethServer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NethServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NethServer.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Retrieve statistics abaout server resources:
 * - load
 * - memory
 * - disk space
 * - uptime
 *
 * @author Giacomo Sanchietti
 */
class Resources extends \Nethgui\Controller\AbstractController
{
    public $sortId = 10;
 
    private $load = array();
    private $memory = array();
    private $uptime = array();
    private $df = array();
    private $cpuNum = 0;

    private function readMemory()
    {
        $fields = array();
        $f = file('/proc/meminfo');
        foreach ($f as $line) {
            $tmp = explode(':',$line);
            $tmp2 = explode(' ', trim($tmp[1]));
            $mb = $tmp2[0] / 1024; # kB -> MB
            $fields[trim($tmp[0])] = ceil($mb);
        }
        return $fields; 
    }

    private function readUptime() {
        $data = file_get_contents('/proc/uptime');
        $upsecs = (int)substr($data, 0, strpos($data, ' '));
        $uptime = array (
            'days' => floor($data/60/60/24),
            'hours' => $data/60/60%24,
            'minutes' => $data/60%60,
            'seconds' => $data%60
        );
        return $uptime;
    }

    private function readDF() {
        $out = array();
        $ret = array();
        exec('/bin/df -P -x tmpfs', $out);
        # Filesystem Size  Used Avail Use% Mount
        for ($i=0; $i<count($out); $i++) {
            if ($i == 0) {
                continue;
            }
            $tmp = explode(" ", preg_replace( '/\s+/', ' ', $out[$i]));
            // skip fs ($tmp[0]) and perc_used ($tmp[4])
            $ret[$tmp[5]] = array('total' => intval($tmp[1]), 'used' => intval($tmp[2]), 'avail' => intval($tmp[3]));
        }
        return $ret;
    }

    private function readCPUNumber() 
    {
        $ret = 0;
        $f = file('/proc/cpuinfo');
        foreach ($f as $line) {
            if (strpos($line, 'processor') === 0) {
                $ret++;
            }
        }
        return $ret;
    }

    public function process()
    {
        $this->load = sys_getloadavg();
        $this->memory = $this->readMemory();
        $this->uptime = $this->readUptime();
        $this->df = $this->readDF();
        $this->cpuNum = $this->readCPUNumber();
    }
 
    public function prepareView(\Nethgui\View\ViewInterface $view)
    {
        if (!$this->load) {
            $this->load = sys_getloadavg();
        }

        $view['load1'] = $this->load[0];
        $view['load5'] = $this->load[1];
        $view['load15'] = $this->load[2];
        
        if (!$this->cpuNum) {
            $this->cpuNum = $this->readCPUNumber();
        }
        $view['cpu_num'] = $this->cpuNum; 

        if ($this->memory) {
            $tmp[] = array($view->translate("mem_total_label"), $this->memory['MemTotal']);
            $tmp[] = array($view->translate("mem_used_label"), $this->memory['MemTotal']-$this->memory['MemFree']);
            $tmp[] = array($view->translate("mem_free_label"), $this->memory['MemFree']);
            $view['memory'] = $tmp;

            $tmp = array();
            $tmp[] = array($view->translate("swap_total_label"), $this->memory['SwapTotal']);
            $tmp[] = array($view->translate("swap_used_label"), $this->memory['SwapTotal']-$this->memory['SwapFree']);
            $tmp[] = array($view->translate("swap_free_label"), $this->memory['SwapFree']);
            $view['swap'] = $tmp;
        }


        if (!$this->uptime) {
            $this->uptime = $this->readUptime();
        }

        $view['days'] = $this->uptime['days'];
        $view['hours'] = $this->uptime['hours'];
        $view['minutes'] = $this->uptime['minutes'];
        $view['seconds'] = $this->uptime['seconds'];
        
        if ($this->df) {
            $tmp = array(); 
            foreach($this->df['/'] as $k=>$v) {
                $tmp[] = array($view->translate($k."_label"),$v);
            } 
            $view['root_df']  = $tmp; 
        }
        
    }
  

}