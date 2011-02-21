<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is lisensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 * 	  the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website's Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attibutes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2011 (or current year) ExamNotes.net inc.
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms;

/**
 * Class represents one document in Mongo collection
 * it has auto-save feature, so if anything has changed in this object
 * it will be saved to Mongo Collection automatically
 *
 * @author Dmitri Snytkine   implements \Serializable
 *
 */
class MongoDoc extends ArrayDefaults implements \Serializable
{
	/**
	 * Object of type Registry
	 * We need Registry and not just oMongo
	 * because we sometimes need to post events
	 * and we can get Dispatcher from Registry
	 *
	 * @var object of type Registry
	 */
	protected $oRegistry;

	/**
	 * Checksum of array data
	 *
	 * @var string
	 */
	protected $md5 = null;

	/**
	 * Flag indicates that data
	 * has already been saved, usually
	 * by manually calling save()
	 *
	 * The desctuctor checks this flag and will
	 * not save data if it was already saved.
	 *
	 * @var bool
	 */
	protected $bSaved = false;

	/**
	 * Name of table which holds
	 * this row.
	 *
	 * If this is undefined then data will not be saved
	 * on update during the save() method call
	 *
	 * This is possible when we want the object to
	 * be of certain type but the object is not really
	 * a representation of any table row.
	 * For example this can be data from OpenSocial
	 *
	 * @var string
	 */
	protected $collectionName = null;


	/**
	 * The name of column that is used for
	 * 'where' clause during the update.
	 *
	 * Usually this is the 'id'
	 *
	 * When the value of the key with this name is present
	 * then during the save() we run the update query,
	 * otherwise we do the insert
	 *
	 * @var string
	 */
	protected $keyColumn = '_id';


	/**
	 * Array of column name to default value
	 * Use this to override any other value
	 * that may already be present either as
	 * a default value for column as defined in table
	 * definition OR the value set
	 * in array $aData
	 * This means that setting value for a column
	 * in $aData will have no effect if the value
	 * for that column is present in this array.
	 *
	 * Values in this array override any value
	 *
	 * @var array
	 */
	protected static $aDefaults = array();

	/**
	 * Array of fileds that should be ignored
	 * when saving to DB (unset these before saving array)
	 *
	 */
	protected $aIgnore = array();

	/**
	 * Minimal value for auto_increment
	 *
	 * @var int
	 */
	protected $minAutoIncrement = 1;


	public static function factory(Registry $oRegistry, $collectionName = null, array $a = array(), $default = ''){
		$o = new static($oRegistry, $collectionName, $a, $default);

		return $o;
	}

	/**
	 * Constructor
	 * @param array $a
	 * @param string $collectionName name of Mongo Collection in which
	 * data of this document belongs
	 *
	 * @param string $default means this value will be returned when
	 * the array key does not exist. Usually this is empty string, but you can
	 * set it to null or false, whatever you want to use for a default (fallback)
	 * value of any array key
	 */
	public function __construct(Registry $oRegistry, $collectionName = null, array $a = array(), $default = '')
	{
		parent::__construct($a, $default);
		$this->oRegistry = $oRegistry;
		$this->collectionName = $collectionName;
		$this->md5 = md5(serialize($a));
	}


	public function setMinAutoIncrement($val){
		$this->minAutoIncrement = (int)$val;

		return $this;
	}

	public function getMinAutoIncrement(){

		return $this->minAutoIncrement;
	}

	/**
	 * It also enforces return type by casting
	 * return value to int if key starts with 'i_'
	 * or to array if keys starts with 'a_'
	 * or to boolean if key starts with 'b_'
	 *
	 * (non-PHPdoc)
	 * @see ArrayDefaults::offsetGet()
	 */
	public function offsetGet($name)
	{
		$ret = parent::offsetGet($name);

		$prefix = substr($name, 0, 2);
		switch($prefix){
			case 'i_':
				$ret = (int)$ret;
				break;

			case 'a_':
				$ret = (array)$ret;
				break;

			case 'b_':
				$ret = (bool)$ret;
				break;

			default:
				$ret = $ret;
		}

		return $ret;
	}

	/**
	 * Internal getter of Registry object
	 * We need this for when the object is unserialized
	 * and thus does not have instance of Registry object anymore
	 *
	 * When object is instantiated the normal way
	 * via constructor it has registry object.
	 *
	 * @return object of type Registry
	 */
	protected function getRegistry(){
		if(!isset($this->oRegistry)){
			$this->oRegistry = Registry::getInstance();
		}

		return $this->oRegistry;
	}



	/**
	 * Setter for $this->keyColumn
	 * @param string $keyColumn
	 * @return object $this
	 */
	public function setKeyColumn($keyColumn)
	{
		$this->keyColumn = $keyColumn;

		return $this;
	}

	public function setCollectionName($name){
		$this->collectionName = $name;

		return $this;
	}

	/**
	 * Use case:
	 * class MongoComments {}
	 * $article = MongoComments::byUid(123123);
	 *
	 * The class extending this class has to exist in order
	 * for this method to even be used.
	 *
	 * A much better way is to get object of this class
	 * dymanically via Registry. Registry will know what to do:
	 * it will create an object of this class, set the collectionName
	 * extracted from requested class and return the object.
	 * Then use by$fieldname($value) method to load the
	 * array.
	 *
	 * @todo this should not be static, should be regular __call()
	 *
	 * @param unknown_type $method
	 * @param unknown_type $arguments
	 *
	 * @return object of this class representing MongoCollection
	 * extracted from class name.
	 */
	public static function __callStatic($method, $arguments) {
		if('by' !== substr(strtolower($method), 0, 2) ){
			throw new \InvalidArgimentException('Unknown method: '.$method);
		}

		$calledClass = get_called_class();
		if('Mongo' !== substr($calledClass, 0, 5)){
			throw new \InvalidArgumentException('Class that extends MongoDoc must begin with "Mongo" prefix followed by name of Mongo Collection. It was: '.$calledClass);
		}

		$collectionName = ucfirst(substr(strtolower($calledClass), 8 ));

		$value = $arguments[0];

		d('$collectionName: '.$collectionName. ' method: '.$method. ' $value: '.$value);

		return Registry::getInstance()->{'Mongo'.$collectionName}->$method($value);
	}

	/**
	 * Use case:
	 * $o->byEmail('something@blank.com')->user_id;
	 * This will case the call to __call(), which will
	 * find the record in mongo collection of this object
	 * finding by field 'email' = 'something@blank.com'
	 * and if record if found it will be set as the underlying
	 * array of the object.
	 * We can then use the object's normal accessor ->user_id
	 * to get value of user_id
	 *
	 *
	 * @param string $method
	 * @param array $arguments
	 * @throws InvalidArgimentException
	 */
	public function __call($method, $arguments){
		if('by' !== substr(strtolower($method), 0, 2) ){
			throw new \InvalidArgumentException('Unknown method: '.$method);
		}

		$column = strtolower(substr($method, 2));
		$value = $arguments[0];

		d('$collectionName: '.$this->collectionName. ' $column: '.$column.' $value: '.$value);

		$a = $this->getRegistry()->Mongo->getCollection($this->collectionName)->findOne(array($column => $value) );

		if(!empty($a)){
			d('got data a: '.print_r($a, 1));
			
			$this->reload($a);
		}

		return $this;
	}



	/**
	 * Getter for $this->md5
	 * @return string
	 */
	public function getChecksum()
	{
		return $this->md5;
	}

	/**
	 * Getter for $this->collectionName
	 * This will be used (among other cases)
	 * from observers when they receive onCollectionInsert
	 * or onCollectionUpdate event, then will be able
	 * to easily find 'which collection'...
	 *
	 * @return string
	 */
	public function getCollectionName()
	{
		return $this->collectionName;
	}

	/**
	 * Replace the content of array with the new one
	 * The new array, if not passed here will be
	 * a result of a database select
	 * based on tableName and columnID
	 *
	 * @param array $a
	 * @return unknown_type
	 */
	public function reload(array $a = array())
	{
		if(empty($a)){
			$kval = $this->offsetGet($this->keyColumn);
			if(!empty($kval)){

				$a = $this->getRegistry()->Mongo->getCollection($this->collectionName)->findOne(array($this->keyColumn => $kval));
				d('got one: '.print_r($a, true));
			}
		}

		$this->exchangeArray($a);
		$this->setChecksum();

		return $this;
	}



	/**
	 * Resets the array to an empty array
	 * deletes the table row from table
	 *
	 * @todo unfinished. Not sure what to do
	 * after the actual row is deleted?
	 * Probably the best solution is to reset this
	 * to an empty array and set a special flag
	 * $this->deleted = true
	 * With this flag the destructor should not
	 * do anything
	 * and attempts to select any value of anything from
	 * this object should result in exception? Maybe....
	 *
	 * Basically once the delete() in invoked it deletes
	 * the row from the database, so this object
	 * is no longer needed and ideally shold just go away
	 * but since it will not be destroyed right away we should
	 * at least throw exceptions when any attemp to use this
	 * object is made
	 *
	 * @return mixed
	 */
	public function delete()
	{

	}

	/**
	 * Sets the $this-md5 to the md5 checksum of array
	 *
	 * @return object $this
	 */
	protected function setChecksum()
	{
		$a = $this->getArrayCopy();
		$this->md5 = md5(serialize($a));
		d('just reset checksum to '.$this->md5.' for object '.
		'object: '.$this->getClass()."\n".
		'hashCode: '.$this->hashCode());

		return $this;
	}

	/**
	 * Saves array of data to
	 * the database table
	 *
	 * @return mixed false if update or insert did not work
	 * OR value of '_id' field on success
	 */
	public function save()
	{
		$kval = $this->offsetGet($this->keyColumn);
		d('kval: '.$kval);

		if( (null !== $kval) && ('' !== $kval) && false !== $kval){

			return $this->update();
		}

		return $this->insert();
	}


	/**
	 * Merges the input array with existing
	 * array. But instead of using array_merge,
	 * it uses the offsetSet() from the foreach loop
	 * This is because the other way to do this is
	 * probably even less efficient - (get
	 * array copy, then merge then exchangeArray)
	 *
	 * Values from input array override values
	 * in existing array if keys are the same.
	 *
	 * @param array $a
	 *
	 * @return object $this
	 */
	public function addArray(array $a)
	{
		$kval = $this->offsetGet($this->keyColumn);
		foreach($a as $key => $val){
			/**
			 * Very important
			 * we don't allow changing of keyColumn value
			 * if one is already set.
			 *
			 * But If keyColumn value is not set (empty) then we
			 * DO
			 * allow to set this value
			 *
			 * IMPORTANT! We are testing empty($kval)
			 * which means that if keyColumn has a value of 0
			 * it will also evaluate to true, since 0 is empty,
			 * thus allowing to override the value
			 * of keyColumn with the new value.
			 *
			 * For this reason it's better to never
			 * store a value 0 as primary key in any table
			 *
			 */
			if($key !== $this->keyColumn || empty($kval)){
				$this->offsetSet($key, $val);
			}
		}

		return $this;
	}


	/**
	 * Inserts data to the table
	 * then selects the data from the database
	 * and repopulates this object.
	 * This is necessary in order to
	 * get values of default column
	 * values that may be defined in the table
	 *
	 * IMPORTANT: upon the insert into Collection
	 * the value of '_id' key may be set to
	 * the _id as generated by the
	 * Mongo driver. This way after we insert the
	 * new row we have access to it's '_id' value
	 * right away.
	 *
	 * @return mixed false on failure or value of _id of inserted doc
	 * which can be MongoId Object or string or int, depending if
	 * you included value of _id in $aValues or let Mongo generate one
	 * By default mongo generates the unique value and it's an object
	 * of type MongoId
	 */
	public function insert()
	{

		if(!$this->checkOffset($this->keyColumn) && $this->minAutoIncrement){
			$_id = $this->getRegistry()->Incrementor->nextValue($this->collectionName, $this->minAutoIncrement);
			$this->offsetSet('_id', $_id);
		}

		$aData = $this->getArrayCopy();

		try{
			$res = $this->getRegistry()->Mongo->insertData($this->collectionName, $aData);
			d('res: '.$res);

		} catch(\MongoException $e){
			e('LampcmsError Unable to insert document into Mongo: '.$e->getMessage());
		}

		if(false === $res && empty($aData['_id'])){

			throw new Exception('Failed to insert data into MongoDB collection: '.$this->collectionName.' aData: '.print_r($aData, 1));
		}

		$this->offsetSet($this->keyColumn, $res);

		/**
		 * If we don't set this to true, then
		 * destructor will attempt to call
		 * the save() method again because it will detect
		 * the change in array because the
		 * value of '_id' in array has changed.
		 *
		 */
		$this->setChecksum();
		$this->getRegistry()->Dispatcher->post($this, 'onCollectionInsert');

		return $res;
	}



	/**
	 * Remove the $this->keyColumns from array?
	 * Not really necessary
	 * and use its value as $wherecolumn
	 *
	 * @todo why don't we update the md5 value?
	 *
	 * @return mixed number of affected rows (which could be 0)
	 * or false in case of some error during update sql statement
	 *
	 */
	protected function update()
	{

		$ret = false;
		$aData = $this->getArrayCopy();
		$whereVal = $this->offsetGet($this->keyColumn);
		try{
			
			$ret = $this->getRegistry()->Mongo->updateCollection($this->collectionName, $aData, $this->keyColumn, $whereVal, __METHOD__);
		} catch (\Exception $e){
			d('could not update MongoCollection $whereVal: '.$whereVal. ' $aData: '.print_r($aData, 1));

			return false;
		}

		/**
		 * Succussfull update should
		 * return number of affected rows
		 * which should be > 0 but could also be 0
		 * which means that no rows were affected but update was still
		 * successfull. This would be the case if update() was run on a table
		 * row with exactly the same data, thus no rows were technically affected
		 *
		 * This is why we must test for false and not for empty()
		 */
		if(false === $ret){
			e('could not update MongoCollection $whereVal: '.$whereVal. ' $aData: '.print_r($aData, 1));

			return false;
		}

		$this->setChecksum();

		d('ret: '.$ret.' $new md5: '.$this->md5);
		$this->getRegistry()->Dispatcher->post($this, 'onCollectionUpdate');

		return $whereVal;
	}


	/**
	 * Update data in Table but ONLY
	 * of changes to array have been detected
	 * @return object $this
	 */
	public function saveIfChanged()
	{

		$a = $this->getArrayCopy();
		if(($this->md5 !== md5(serialize($a))) ){
			d('Something was changed and not saved in table: '.$this->collectionName."\n".
			'orig md5: '.$this->md5.' new md5: '.md5(serialize($a))."\n".
			'new array: '.print_r($a, 1)."\n".
			'object: '.$this->getClass()."\n".
			'hashCode: '.$this->hashCode());

			try{
				$ret = $this->save();
			} catch(\Exception $e){
				e('LampcmsError unable to save array exception data: '.$e->getFile().' line: '.$e->getLine(). ' ' .$e->getMessage());
			}
		} else {
			d('No changes to original array, update is not necessary');
		}

		return $this;

	}

	/**
	 * Set the this->bSaved flag to true
	 * This will prevent the object from trying
	 * to save array to database from __destruct()
	 *
	 * @return object $this
	 */
	public function setSaved()
	{
		$this->bSaved = true;

		return $this;
	}

	/**
	 * Destructor causes
	 * the save() to be called automatically
	 * when object is destroyed
	 *
	 * @return void
	 */
	public function __destruct()
	{
		$a = $this->getArrayCopy();
		if(!$this->bSaved && ($this->md5 !== md5(serialize($a))) && (!empty($a))){
			d('Something was changed and not saved in table: '.$this->collectionName."\n".
			'orig md5: '.$this->md5.' new md5: '.md5(serialize($a))."\n".
			'new array: '.print_r($a, 1)."\n".
			'object: '.$this->getClass()."\n".
			'hashCode: '.$this->hashCode());

			try{
				$this->save();
			} catch(\Exception $e){
				e('Unable to save array. '.$e->getFile().' line: '.$e->getLine(). ' ' .$e->getMessage());
			}
		}
	}


	/**
	 * Merge this object's array with array of
	 * table definitions where keys are
	 * column names and values are default values
	 *
	 * @return object $this
	 */
	public function applyDefaults()
	{
		if(isset(static::$aDefaults) && !empty(static::$aDefaults)){
			$a = $this->getArrayCopy();

			$a = array_merge($aDefaults, $a);


			$this->md5 = md5(serialize($a));
			$this->exchangeArray($a);
		}

		return $this;
	}


	public function serialize()
	{
		$a = array('array' => $this->getArrayCopy(),
		            'collectionName' => $this->collectionName,
					'md5' => $this->md5,
					'bSaved' => $this->bSaved,
					'keyColumn' => $this->keyColumn,
					'defaultValue' => $this->defaultValue);

		unset($this->oRegistry);

		return serialize($a);
	}


	public function unserialize($serialized)
	{
		$a = unserialize($serialized);
		$this->exchangeArray($a['array']);
		$this->collectionName = $a['collectionName'];
		$this->defaultValue = $a['defaultValue'];
		$this->bSaved = $a['bSaved'];
		$this->keyColumn = $a['keyColumn'];
		$this->md5 = $a['md5'];
	}
}
