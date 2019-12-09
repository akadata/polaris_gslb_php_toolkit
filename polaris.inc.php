<?php
//  _    ___   ___  ___  ___
// | |  | _ ) / _ \| _ \/ __|
// | |__| _ \| (_) |   / (_ |
// |____|___(_)___/|_|_\\___|
//           Loadbalancer.org
// --- GSLB Pools - Andruw Smalley 
//  December 2019 v1 Polaris GSLB PHP Tools

//
//

//
// Examples; 
// $gslb=
//
// 
// $datacenter="dc1";
// $subnet="72.31.100.10/32";
// del_topology($datacenter,$subnet);
// add_topology($datacenter,$subnet);
// $subnet="";
// del_topology($datacenter);

$polaris_topology = "/opt/polaris/etc/polaris-topology.yaml";
$polaris_lb= "/opt/polaris/etc/polaris-lb.yaml";
function del_topology($datacenter='',$subnet='') {
    // delete pools or datacenters from the topology  and write (yaml)
    // $datacenter (string)
    // $subnet (string)
    // supply datacenter only and the entire datacenter will be removed
    // supply the subnet and datacenter to delete that subnet
    // search datacenters and delete as per the key found
    $topology=read_topology();
    if(!empty($datacenter) && !empty($subnet)) {
         foreach($topology as $id=>$topo) { // walk dc's
            if($datacenter==$topo['datacenter']) {
                foreach($topology[$id]['subnets'] as $key=>$value) {
                    if($subnet==$value){
                        unset($topology[$id]['subnets'][$key]);
                        $topology[$id]['subnets']=array_values($topology[$id]['subnets']);
                        $topology=array_values($topology);
                        write_topology($topology);
                        return true;  // deleted subnet from topology
                    }
                }    
            }
        }
    } elseif($datacenter!='' && $subnet=='') {
         foreach($topology as $id=>$topo) { // walk dc's
            if($datacenter==$topo['datacenter']) {
                unset($topology[$id]);
                $topology=array_values($topology);
                write_topology($topology);
                return true; // deleted entire topology as no subnet was not defined
            }
        }
    }
    return false; 
}

function add_topology($datacenter='',$subnet='') {
    // add_topology()  
    // $datacenter = (string)
    // $dubnet = (string)
    // $topology = (array) from read_topology()
    $topology=read_topology();
    if(isset($topology) && !is_array($topology[0])) { // there is no topology
        $topology[0]['datacenter']=$datacenter;
        $topology[0]['subnets'][]=$subnet;
        write_topology($topology);
        return true;
    }
    // we have topology
    $dc=0;
    foreach($topology as $id=>$topo) { // walk dc's
        if($datacenter==$topo['datacenter']) { // we found a DC
            $dc=1;
            if(!in_array($subnet,$topo['subnets'])) { // we can add a subnet
                $topology[$id]['subnets'][]=$subnet;  
                write_topology($topology);
                return true;
            }
        }
    }
    if($dc==0) { // no dc found
        $tc=count($topology);
        $topology[$tc]['datacenter']=$datacenter;
        $topology[$tc]['subnets'][]=$subnet;
        write_topology($topology);
        return true;
    }
    return false;
}

function read_topology() {    
    // read topology YAML in to an array.
    global $polaris_topology;
    $topology=array();
    $gslb_topology= fopen($polaris_topology,"r");
    $t=0;     
    while(! feof($gslb_topology) ) {  // do polaris config
        $parts=fgets( $gslb_topology );
        if($parts!="") {
            $line=explode(":",$parts);
            $a=trim($line[0]);
            if(isset($line[1])) {
                $b=trim($line[1]);
            }
            if(count($line)==2) {
                if(!isset($ttop)) {
                     $ttop=0;
                 }
                 if($ttop==1) {
                    $t++;
                }
                $ttop=1;
                $topology[$t]['datacenter']=trim($a);
            } elseif(count($line)==1) {
                $subnet=trim(str_replace("-","",$a));
                $hascidr=explode("/",$subnet);
                if(!empty($subnet) && count($hascidr)==2 && $hascidr[1]>=0 && $hascidr[1]<=32) {
                    $topology[$t]['subnets'][]=$subnet;
                }
            }
        } 
    }   
    fclose($gslb_topology);
    return $topology;
}

function write_topology($topology='') {
    global $polaris_topology;
    // write the polaris topology file input (array) output (yaml)
    // simply write the array back to YAML
    $yaml_topology="# loadbalancer.org generated file, if you update manual configuration it will be reflected here\n";
    foreach($topology as $id=>$topo) {
        extract($topo);
        $yaml_topology .= $datacenter.":\n";
        if(isset($subnets) && is_array($subnets)) {
            foreach($subnets as $id=>$subnet) {
                $yaml_topology .= "- ".$subnet."\n";
            }
        }
    }     
    // echo $yaml_topology;

    $gslb_topology= fopen($polaris_topology,"w") or die("\n\n--- Unable to open file!\n");
    fwrite($gslb_topology, $yaml_topology);
    fclose($gslb_topology);
    return true;
}

// dont think I use this now. 
function reorder($topology) {
    return $topology=array_values($topology);
}

function read_gslb() {
    // read the polaris topology file and return (array)
    global $polaris_lb;
    $gslb_polaris = fopen($polaris_lb,"r") or die("Can not open polaris-lb.yaml");
    $dn=0;    
    $do=0;    
    $m=0;
    while(! feof($gslb_polaris) ) {
        $parts=trim( fgets( $gslb_polaris ) );
        $line=explode( ":", $parts );
        $a=trim( $line[0] );
        if(isset($line[1])) {
            $c=trim( $line[1] );
        } else {            
            $c='';        
        }
        if($a=="pools") {            
            $do=1;       
        } 
        if($a=="globalnames") {            
        $do=2; // what do we want to read?        
    }
    $a=str_replace("- ","",$a);
        if(isset($do)) {
            switch($do) {
                case 1:
                if(!in_array($a,array("members","monitor_params","pools","expected_codes")) && !is_numeric($a) && $c=="" && $do=1) { 
                    if(!isset($iitr)){
                        $iitr=0;
                    } else {
                        $iitr++;
                    }
                    if($a!="") {
                     $gslb["pools"][$iitr]['poolname']=$a;
                    }
                    if($pe=0) {
                        $gslb["pools"][$iitr]['pool']=$a."-----";
                    }
                }
                if(is_numeric(trim($a))) {
                    // do codes here because it should work for expected_codes
                    // what else has possible return codes? ill need to check for that!
                    
                    $gslb["pools"][$iitr]["monitor_params"]['expected_codes'][]=$a;
                } else {
                    if($a=="members") {
                        $m=0;                        
                        $mi=0;
                    }
                    if(in_array($a,array("ip","name","weight","monitor_ip"))) { 
                        if($a=="ip" && $mi==1) {
                            $m++;                        
                        } else {                            
                            $mi=1;                        
                        }
                        $gslb["pools"][$iitr]["members"][$m][$a]=$c;
                    }
                    if(in_array($a,array("monitor","lb_method","fallback","max_addrs_returned"))) {
                         $gslb["pools"][$iitr][$a]=$c;
                    }
                    if(in_array($a,array("use_ssl","hostname","port","url_path","interval","timeout","retries","send_string","match_re"))) {
                         $gslb["pools"][$iitr]["monitor_params"][$a]=$c;
                    }
                }
                break;
                case 2:
                if($c=="" && $a!="globalnames") {
                    if(!isset($ditr)){
                        $ditr=0;
                    } else {
                        $ditr++;
                    }
                    if($a!="") {
                        $gslb['globalnames'][$ditr]['domain']=$a;
                    }

                }
                if($a=="pool" || $a=="ttl") {
                    $gslb['globalnames'][$ditr][$a]=$c;
                }
                break;
            }
            $lv=$a;
        }
    }
    fclose($gslb_polaris);
    return $gslb;
}


// will write the pools and topology to the lb topology yaml file
// takes (array) writes (yaml)
function write_gslb($gslb='') {  //,$cfg_gslb_polaris_lb
            global $polaris_lb;
            // gslb_lb is the file which holds pools and globalnames
            $gslb_lb= "# Polaris GSLB PHP Toolkit (c) Andrew Smalley - Loadbalancer.org configuration generated. \n";
            $gslb_lb .= "\n\npools:\n";

    // start pools 
    foreach($gslb['pools'] as $id =>$po) { 
        extract($po);
            $gslb_lb .= "    ".$poolname.":\n";
            $gslb_lb .= "    monitor: ".$monitor."\n";
            $gslb_lb .= "    monitor_params:\n";
            extract($monitor_params);
            $pools .= "        use_ssl: ".$use_ssl."\n";
            $pools .= "        hostname: ".$hostname."\n";
            $pools .= "        url_path: ".$url_path."\n";
        }
            $gslb_lb .= "    lb_method: ".$lb_method."\n";
            $gslb_lb .= "    fallback: ".$fallback."\n";
            $gslb_lb .= "    members:\n";
        foreach($members as $member) {
            extract($member);
            $gslb_lb .= "     - ip: ".$ip."\n";
            $gslb_lb .= "       name: ".$name."\n";
            $gslb_lb .= "       weight: ".$weight."\n";
        }
    }
    // start globalnames
            $gslb_lb .= "\n\nglobalnames:\n";
    foreach($gslb['globalnames'] as $key=>$value) {
        extract($value);
            $gslb_lb .=  "    ".$domain.":\n";
            $gslb_lb .=  "       pool: ".$pool."\n";
            $gslb_lb .=  "       ttl: ".$ttl."\n";
    }
    // open the polaris-lb.yaml file and write the contents. 
    $gslb_polaris_lb = fopen($polaris_lb,"w") or die("Can not open polaris-lb.yaml");
    fwrite($gslb_polaris_lb, $gslb_lb);
    fclose($gslb_polaris_lb);
    return true;
}
