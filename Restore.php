<?php
namespace FreePBX\modules\Dictate;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
	public function runRestore($jobid){
		$configs = $this->getConfigs();
		foreach ($configs as $ext => $conf) {
			$this->FreePBX->Dictate->add($ext, $conf['enabled'], $conf['format'], $conf['email'], $conf['from']);
		}
	}
	public function processLegacy($pdo, $data, $tables, $unknownTables){
		$ampuser = $data['astdb']['AMPUSERS'];
		if(!$ampusers){
			return $this;
		}
		$data = [];
		foreach ($ampusers as $key => $value) {
			if(strpos($key,'dictate') === false){
				return $this;
			}
			$tmp = explode('/', $key);
			if(!is_numeric($tmp[0]) || $tmp[1] !== 'dictate'){
				continue;
			}
			$data[$tmp[0]][$tmp[2]] = $val;
		}
		foreach ($data as $key => $value) {
			$this->FreePBX->Dictate->add($key, $value['enabled'], $value['format'], $value['email'], $value['from']);
		}
	}
}
