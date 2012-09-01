<?php
App::uses('CakeEventListener', 'Event');
/**
 * Local Image Processor Event Listener for the CakePHP FileStorage plugin
 *
 * @copy 2012 Florian Krämer
 * @license MIT
 */
class LocalImageProcessingListener implements CakeEventListener {

/**
 * It is required to get the file first and write it to a tmp file
 *
 * The adapter might not be one that is using a local file system, so we first
 * get the file from the storage system, store it locally in a tmp file and 
 * later load the new file that was generated based on the tmp file into the 
 * storage adapter. This method here just generates the tmp file.
 *
 * @param 
 * @param 
 * @return 
 */
	protected function _tmpFile($Storage, $path) {
		try {
			$tmpFile = TMP . String::uuid();
			$imageData = $Storage->read($path);
			file_put_contents($tmpFile, $imageData);
			return $tmpFile;
		} catch (Exception $e) {
			return false;
		}
	}

/**
 * Check if the event can be processed
 *
 * @param CakeEvent $Event
 * @return boolean
 */
	protected function _checkEvent($Event) {
		$Model = $Event->subject();
		return ($Model instanceOf ImageStorage && isset($Event->data['record'][$Model->alias]['adapter']) && $Event->data['record'][$Model->alias]['adapter'] == 'Local');
	}

	public function implementedEvents() {
		return array(
			'ImageStorage.createVersion' => 'createVersions',
			'ImageStorage.removeVersion' => 'removeVersions',
			'ImageStorage.afterSave' => 'afterSave',
			'ImageStorage.afterDelete' => 'afterDelete'
			//'ImageStorage.beforeDelete' => 'removeVersion'
		);
	}

	public function createVersions($Event) {
		if ($this->_checkEvent($Event)) {
			$Model = $Event->subject();
			$Storage = $Event->data['storage'];
			$record = $Event->data['record'][$Model->alias];

			$tmpFile = $this->_tmpFile($Storage, $record['path']);

			foreach ($Event->data['operations'] as $version => $operations) {
				$hash = $Model->hashOperations($operations);
				$string = substr($record['path'], 0, - (strlen($record['extension'])) -1);
				$string .= '.' . $hash . '.' . $record['extension'];

				if ($Storage->has($string)) {
					return true;
				}

				$image = $Model->processImage($tmpFile, null, array('format' => $record['extension']), $operations);
				$result = $Storage->write($string, $image->get($record['extension']), true);
			}

			unlink($tmpFile);

			$Event->stopPropagation();
		}
	}

	public function removeVersions($Event) {
		if ($this->_checkEvent($Event)) {
			$Model = $Event->subject();
			$Storage = $Event->data['storage'];
			$record = $Event->data['record'][$Model->alias];

			foreach ($Event->data['operations'] as $version => $operations) {
				$hash = $Model->hashOperations($operations);
				$string = substr($record['path'], 0, - (strlen($record['extension'])) -1);
				$string .= '.' . $hash . '.' . $record['extension'];

				try {
					$Storage->delete($string);
				} catch (Exception $e) {
					// No need to do anything here
				}
			}

			$Event->stopPropagation();
		}
	}

	public function afterDelete($Event) {
		if ($this->_checkEvent($Event)) {
			$path = Configure::read('Media.basePath') . $this->record[$this->alias]['path'];
			if (is_dir($path)) {
				$Folder = new Folder($path);
				return $Folder->delete();
			}
			return false;
		}
	}

	public function afterSave($Event) {
		if ($this->_checkEvent($Event)) {
			
		}
	}

}