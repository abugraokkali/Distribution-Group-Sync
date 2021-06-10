<?php
/**
 * Constants
 */
define("DefaultGroupDN", "CN=Gruplar,CN=Users,DC=bugra,DC=lab");
$logfile = fopen("logfile.txt", "w");

/** 
 * Connection 1
*/
$domainname1= "ali.lab";
$user1 = "administrator@".$domainname1;
$pass1 = "123123Aa";
$server1 = 'ldaps://192.168.1.68';
$port1="636";
$binddn1 = "DC=ali,DC=lab";
$ldap1 = ldap_connect($server1);
ldap_set_option($ldap1, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap1, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
ldap_set_option($ldap1, LDAP_OPT_REFERRALS, 0);

$bind1=ldap_bind($ldap1, $user1, $pass1);
if (!$bind1) {
    exit('Binding failed');
}

/** 
 * Connection 2
*/
$domainname2= "bugra.lab";
$user2 = "administrator@".$domainname2;
$pass2 = "123123Aa";
$server2 = 'ldaps://192.168.1.69';
$port2="636";
$binddn2 = "DC=bugra,DC=lab";
$ldap2 = ldap_connect($server2);
ldap_set_option($ldap2, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap2, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
ldap_set_option($ldap2, LDAP_OPT_REFERRALS, 0);

$bind2=ldap_bind($ldap2, $user2, $pass2);
if (!$bind2) {
    exit('Binding failed');
}

/**
 *Globals
 */
$dn_list1 = find_dn_list($ldap1,$binddn1);
$dn_list2 = find_dn_list($ldap2,$binddn2);

/**
 * Distribution gruplari ve memberlarini ceker, bir array halinde doner.
 */
function find_groups($ldap,$binddn,$domainname,$number){

    //distribution gruplarin bulunmasi
    $filter = "(&(objectCategory=group)(!(groupType:1.2.840.113556.1.4.803:=2147483648)))";
    $result = ldap_search($ldap, $binddn, $filter);
    $entries = ldap_get_entries($ldap,$result);

    $data['count'] = $entries['count'];
    $data['domainName'] = $domainname;

    $numberOfGroups = $entries['count'];
    for($i=0 ; $i<$numberOfGroups ; $i++){
        
        $name = $entries[$i]['name'][0];
        $data[$i] = $name;
        $data[$name] = array();
        //eger uyesi varsa
        if (array_key_exists('member', $entries[$i]) ){
            $numberOfMembers = $entries[$i]['member']['count'];
        }
        else{
            $numberOfMembers = 0; 
        }
    
        for($j=0 ; $j<$numberOfMembers ; $j++ ){

            $member = $entries[$i]['member'][$j];
            if($number == 1){
                $dn_list1 = $GLOBALS["dn_list1"];
                $samaccountname = array_search($member,$dn_list1);
                array_push($data[$name], $samaccountname);
            }
            if($number == 2){
                $dn_list2 = $GLOBALS["dn_list2"];
                $samaccountname = array_search($member,$dn_list2);
                array_push($data[$name], $samaccountname);
            }
            
            /*$pos1 = stripos($member, ',');
            $member = substr($member,0,$pos1);
            $pos2 = stripos($member, '=');
            $member = substr($member,$pos2+1);
            array_push($data[$name], $member);*/
            
        }

    }
    return $data;
}

/**
 * Parametre olarak verilen domaindeki tum userlarin ve dist. gruplarin 
 * DN'lerini ceker, username => DN seklinde ikililerden olusan bir array doner.
 */
function find_dn_list($ldap,$binddn){
    
    $filter = "objectCategory=user";
    $result = ldap_search($ldap, $binddn, $filter);
    $entries = ldap_get_entries($ldap,$result);
    $numberOfUsers = $entries['count'];
    for($i=0 ; $i<$numberOfUsers ; $i++){
    
        $samaccountname = $entries[$i]['samaccountname'][0];
        $data[$samaccountname] = $entries[$i]['distinguishedname'][0];    

    }
    $filter = "(&(objectCategory=group)(!(groupType:1.2.840.113556.1.4.803:=2147483648)))";
    $result = ldap_search($ldap, $binddn, $filter);
    $entries = ldap_get_entries($ldap,$result);
    $numberOfGroups = $entries['count'];
    for($i=0 ; $i<$numberOfGroups ; $i++){
    
        $name = $entries[$i]['name'][0];
        $data[$name] = $entries[$i]['distinguishedname'][0];    

    }
    return $data;
   
}

/**
 * Global variable'lar aracigiliyla DN listesini gunceller. Bu isleme yeni bir grup
 * eklendiginde veya silindiginde ihtiyac duyulur.
 */
function update_dn_list($name,$dn){

    $ldap2 = $GLOBALS["ldap2"];
    $binddn2 = $GLOBALS["binddn2"];
    $GLOBALS["dn_list2"] = find_dn_list($ldap2,$binddn2);
    //$dn_list2 = $GLOBALS["dn_list2"];
    //array_push($dn_list2,$name,$dn);
    //print_r($dn_list2);
}

/**
 * find_groups'un ciktisi olan iki dist. group listesini karsilastirir
 * ve senkronizyon icin gerekli islemlerin ne olduguna karar verir.
 */
function sync($data1,$data2){

    for($i=0 ; $i<$data1['count']; $i++){

        $groupName = $data1[$i];
        //2. domainde eksik grup varsa; grubu ac ve uyelerini doldur.
        if (!in_array($groupName, $data2)) {
            create_group($groupName);
            add_members($groupName,$data1,$data2);  
        }
        //ortak grup varsa; 2. domaindeki gruba eksik uyeleri ekle.
        else{
            add_members($groupName,$data1,$data2);  
        }

    }
    
    for($i=0 ; $i<$data2['count']; $i++){

        $groupName = $data2[$i];
        //2. domainde fazladan grup varsa; grubu sil
        if (!in_array($groupName, $data1)) {
            delete_group($groupName);
        }
        //ortak grup varsa; 2. domaindeki gruptan fazla uyeleri sil.
        else{
            remove_members($groupName,$data1,$data2);
        }
        
    }
}

/**
 * 2. domainde eksik olan grubu ekler ve DN listesini gunceller.
 */
function create_group($groupName){
    $logfile = $GLOBALS["logfile"];
    $ldap2 = $GLOBALS["ldap2"];

    $group_info["objectClass"] = "top";
    $group_info["objectClass"] = "group";
    $group_info["groupType"] = 2;
    $group_info["instanceType"] = 4;
    $group_info["name"] = $groupName;

    $dn = 'CN='.$groupName.','.DefaultGroupDN;
    ldap_add($ldap2, $dn,$group_info);
    update_dn_list($groupName,$dn);
    fwrite($logfile, $groupName." isimli grup eklendi.\n");
    //TODO :  Update DN
}

/**
 * 2. domainde fazladan olan grubu siler ve DN listesini gunceller.
 */
function delete_group($groupName){

    $logfile = $GLOBALS["logfile"];
    $dn_list2 = $GLOBALS["dn_list2"];
    $ldap2 = $GLOBALS["ldap2"];
    $dn = $dn_list2[$groupName];
    ldap_delete($ldap2, $dn);
    fwrite($logfile, $groupName." isimli grup silindi.\n");

}

/**
 * Parametre olarak verilen gruba eksik uyelerini ekler. 
 * (eger o isme sahip bir user o domainde yoksa eklemez.)
 */
function add_members($groupName,$data1,$data2){
    
    $logfile = $GLOBALS["logfile"];
    $ldap2 = $GLOBALS["ldap2"];
    $dn_list2 = $GLOBALS["dn_list2"];

    //data2'nin guncellemesi gerekiyor cunku yeni grup eklendi.
    $data2 = find_groups($GLOBALS["ldap2"],$GLOBALS["binddn2"],$GLOBALS["domainname2"],2);
    
    $numberOfMembers = count($data1[$groupName]);
            
    for($j=0 ; $j<$numberOfMembers; $j++){

        $memberName = $data1[$groupName][$j];
        //1. domaindeki bir gruptaki user diğer domaindaki ayni grupta yoksa
        if (!in_array($memberName, $data2[$groupName])){

            //2. domainde eklenmeye calisilan user 2. domainde tanimli bir usersa
            if(array_key_exists($memberName, $dn_list2) ){
                //print_r($groupName."\n\n");
                $dn = $dn_list2[$groupName];
                $group_info['member'] = $dn_list2[$memberName];
                ldap_mod_add($ldap2,$dn,$group_info);
                update_dn_list($memberName,$dn);
                fwrite($logfile, $groupName." grubundan ".$memberName." kullanicisi silindi.\n");

            }
            else{
                //TODO User Create Edilmeli
            }
        }
    }
    

}

/**
 * Parametre olarak verilen grubta fazladan olan uyelerini siler. 
 */
function remove_members($groupName,$data1,$data2){
    
    $logfile = $GLOBALS["logfile"];
    $ldap2 = $GLOBALS["ldap2"];
    $dn_list2 = $GLOBALS["dn_list2"];

    //$data2 = find_groups($GLOBALS["ldap2"],$GLOBALS["binddn2"],$GLOBALS["domainname2"]);
    
    $numberOfMembers = count($data2[$groupName]);

    for($j=0 ; $j<$numberOfMembers; $j++){
        $memberName = $data2[$groupName][$j];

        if(!in_array($memberName, $data1[$groupName])){
            $dn = $dn_list2[$groupName];
            $group_info['member'] = $dn_list2[$memberName];
            ldap_mod_del($ldap2, $dn, $group_info);  
            fwrite($logfile, $groupName." grubundan ".$memberName." kullanicisi silindi.\n");

        }
    }

}

/**
 * Main fonksiyon olarak dusunulebilir, sync oncesi ve sonrasi bastirilir. 
 */
function run(){

    $ldap1 = $GLOBALS["ldap1"];
    $ldap2 = $GLOBALS["ldap2"];
    $binddn1 = $GLOBALS["binddn1"];
    $binddn2 = $GLOBALS["binddn2"];
    $domainname1 = $GLOBALS["domainname1"];
    $domainname2 = $GLOBALS["domainname2"];

    $data1 = find_groups($ldap1,$binddn1,$domainname1,1);
    $data2 = find_groups($ldap2,$binddn2,$domainname2,2);

    print_r("\n ### Before the synchronization ###");
    print_r("\n");
    print_r($data1);
    print_r("\n");
    print_r($data2);
    
    sync($data1,$data2);

    print_r("\n");
    print_r("\n ### After synchronization ###");
    print_r("\n");
    
    $data1 = find_groups($ldap1,$binddn1,$domainname1,1);
    $data2 = find_groups($ldap2,$binddn2,$domainname2,2);

    print_r("\n");
    print_r($data1);
    print_r("\n");
    print_r($data2);
    print_r("\n");

    
}
/*
print_r($dn_list1);
print_r("\n");
print_r($dn_list2);
print_r("\n");*/
run();
ldap_close($ldap1);
ldap_close($ldap2);
