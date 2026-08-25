<?php
namespace FreePBX\modules\Dictate;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
	public function runRestore(){
		$configs = $this->getConfigs();
		foreach (($configs['data'] ?? []) as $ext => $conf) {
			$this->restoreConfiguration($ext, $conf);
		}
		if(!empty($configs['features']) && is_array($configs['features'])) {
			$this->importFeatureCodes($configs['features']);
		}
	}
	public function processLegacy($pdo, $data, $tables, $unknownTables){
		$ampusers = $data['astdb']['AMPUSER'] ?? [];
		if(!$ampusers){
			return $this;
		}
		$configs = [];
		foreach ($ampusers as $key => $value) {
			$key = (string)$key;
			if(strpos($key,'dictate') === false){
				continue;
			}
			$tmp = explode('/', $key);
			if(count($tmp) < 3 || !is_numeric($tmp[0]) || $tmp[1] !== 'dictate'){
				continue;
			}
			$configs[$tmp[0]][$tmp[2]] = $value;
		}
		foreach ($configs as $ext => $conf) {
			$this->restoreConfiguration($ext, $conf);
		}
		$this->restoreLegacyFeatureCodes($pdo);
	}

	private function restoreConfiguration($ext, $conf){
		if(!is_array($conf)) {
			return;
		}
		$encodedFrom = (string)($conf['from'] ?? '');
		$from = base64_decode($encodedFrom, true);
		if($from === false) {
			$from = $encodedFrom;
		}
		$this->FreePBX->Dictate->update(
			$ext,
			$conf['enabled'] ?? 'disabled',
			$conf['format'] ?? 'ogg',
			$conf['email'] ?? '',
			$from
		);
	}
}
