<?php
namespace FreePBX\modules;
use BMO;
use FreePBX_Helpers;

class Dictate extends FreePBX_Helpers implements BMO {
    public function install(){}
    public function uninstall(){}
    public function doConfigPageInit($page){}
    public function getActionBar($request){}
    public function getRightNav($request){}

    public function listAll(){
        if(!$this->FreePBX->astman->connected()){
            return [];
        }
        $final = [];
        $raw = $this->FreePBX->astman->database_show('AMPUSER');
        foreach ($raw as $key => $value) {
            $parts = explode('/', $key);
            if($parts[2] !== 'dictate'){
                continue;
            }
            $final[$parts[1]][$parts[3]] = $value;
        }
        return $final;
    }
    public function update($ext, $ena, $fmt, $email, $from){
        if(!$this->FreePBX->astman->connected()){
            return $this;
        }
        $this->FreePBX->astman->database_put('AMPUSER', $ext.'/dictate/enabled', $ena);
        $this->FreePBX->astman->database_put('AMPUSER', $ext.'/dictate/format', $fmt);
        $this->FreePBX->astman->database_put('AMPUSER', $ext.'/dictate/email', $email);
        $this->FreePBX->astman->database_put('AMPUSER', $ext.'/dictate/from', base64_encode($from));
        return $this;
    }
    public function delete($ext){
        if(!$this->FreePBX->astman->connected()){
            return $this;
        }
        $this->FreePBX->astman->database_deltree("AMPUSER/$ext/dictate");
        return $this;
    }
}