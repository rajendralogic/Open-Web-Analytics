<?php

//
// Open Web Analytics - An Open Source Web Analytics Framework
//
// Copyright 2006 Peter Adams. All rights reserved.
//
// Licensed under GPL v2.0 http://www.gnu.org/copyleft/gpl.html
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.
//
// $Id$
//

//require_once(OWA_BASE_DIR.DIRECTORY_SEPARATOR.'owa_lib.php');
require_once(OWA_BASE_CLASS_DIR.'pagination.php');
require_once(OWA_BASE_CLASS_DIR.'timePeriod.php');

/**
 * Metric
 * 
 * @author      Peter Adams <peter@openwebanalytics.com>
 * @copyright   Copyright &copy; 2006 Peter Adams <peter@openwebanalytics.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GPL v2.0
 * @category    owa
 * @package     owa
 * @version		$Revision$	      
 * @since		owa 1.0.0
 */
class owa_metric extends owa_base {

	/**
	 * Current Time
	 *
	 * @var array
	 */
	var $time_now = array();
	
	/**
	 * Data
	 *
	 * @var array
	 */
	var $data;
	
	/**
	 * The params of the caller, either a report or graph
	 *
	 * @var array
	 */
	var $params = array();
		
	/**
	 * The lables for calculated measures
	 *
	 * @var array
	 */
	var $labels = array();
	
	/**
	 * Page results	 
	 *
	 * @var boolean
	 */
	var $page_results = false;
	
	/**
	 * Data Access Object
	 *
	 * @var object
	 */
	var $db;
	
	/**
	 * The dimensions to groupby
	 *
	 * @var array
	 */
	var $dimensions = array();
	
	/**
	 * The Number of Dimensions to groupby
	 *
	 * @var integer
	 */
	var $dimensionCount;
	
	/**
	 * The table/column or denormalized dimensions 
	 * associated with this metric
	 *
	 * @var array
	 */
	var $denormalizedDimensions = array();
	
	var $_default_offset = 0;
	
	var $pagination;
	
	var $page;
	
	var $limit;
	
	var $order;
	
	var $table;
	
	var $select;
	
	var $time_period_constraint_format = 'timestamp';
	
	var $constraint_operators;
	
	function __construct($params = array()) {
		
		if (!empty($params)):
			$this->params = $params;
		endif;
		
		// Setup time and query periods
		//$this->time_now = owa_lib::time_now();
		//if (!$this->db) {
			$this->db = owa_coreAPI::dbSingleton();
		//}
		
		
		$this->pagination = new owa_pagination;
		
		$this->constraint_operators = array('==',
											'!=',
											'>=', 
											'<=',
											'>' ,
											'<' ,
											'=~',
											'!~',
											'=@',
											'!@');
		
		
		return parent::__construct();
	}
	
	
	/*
	 * Applies overrides specified in the request to the params of the metric.
	 * 
	 */
	function applyOverrides($params = array()) {
		
		foreach ($params as $k => $v) {
			
			if (!empty($v)):
				if (is_array($v)):
					if (!empty($this->params[$k])):
						$this->params[$k] = array_merge($this->params[$k], $v);
					endif;
				else:
					$this->params[$k] = $v;
				endif;
				
				
			endif;
		
		}
		
		return;
	}
	
	/**
	 * @depricated
	 * @remove
	 */
	function applyOptions($params) {
	
		// apply constraints
		if (array_key_exists('constraints', $params)) {
			
			foreach ($params['constraints'] as $k => $v) {
				
				if(is_array($v)) {
					$this->setConstraint($k, $v[1], $v[0]);
				} else {
					$this->setConstraint($k, $value);	
				}				
			}
		}
		
		// apply limit
		if (array_key_exists('limit', $params)) {
			$this->setLimit($params['limit']);
		}
		
		// apply order
		if (array_key_exists('order', $params)) {
			$this->setOrder($params['order']);
		}
		
		// apply page
		if (array_key_exists('page', $params)) {
			$this->setOrder($params['page']);
		}
		
		// apply offset
		if (array_key_exists('offset', $params)) {
			$this->setOrder($params['offset']);
		}
		
		// apply format
		if (array_key_exists('format', $params)) {
			//$this->setFormat($params['format']);
		}
		
		// apply period
		if (array_key_exists('period', $params)) {
			$this->setFormat($params['period']);
		}
		
		// apply start date
		if (array_key_exists('startDate', $params)) {
			$this->setFormat($params['startDate']);
		}

		// apply end date
		if (array_key_exists('endDate', $params)) {
			$this->setFormat($params['endDate']);
		}
	}
	
	function setConstraint($name, $value, $operator = '') {
		
		if (empty($operator)):
			$operator = '=';
		endif;
		
		if (!empty($value)):
			$this->params['constraints'][$name] = array('operator' => $operator, 'value' => $value, 'name' => $name);
		endif;
		
		return;

	}
	
	function setConstraints($array) {
	
		if (is_array($array)) {
			
			$this->params['constraints'] = array_merge($array, $this->params['constraints']);
	
		}
	}
	
	function constraintsStringToArray($string) {
		
		if ($constraints) {
			
			$constraints = explode(',', $string);
			
			$constraint_array = array();
			
			foreach($constraints as $constraint) {
				
				foreach ($this->constraint_operators as $operator) {
					
					if (strpos($constraint, $operator)) {
						list ($name, $value) = split($operator, $constraint);
						$constraint_array[$name] = array('name' => $name, 'value' => $value, 'operator' => $operator);
						break;
					}
				}
			}
			
			return $constraint_array;
		}
		
	
	}
	
	function setLimit($value) {
		
		if (!empty($value)):
		
			$this->limit = $value;
		
		endif;
	}
	
	function setOrder($value) {
		
		if (!empty($value)):
		
			$this->params['order'] = $value;
		
		endif;
	}
	
	function setSort($column, $order) {
		
		$this->params['orderby'][] = array($column, $order);
	}
	
	function setSorts($array) {
		
		if (is_array($array)) {
			
			if (!empty($this->params['orderby'])) {
				$this->params['orderby'] = array_merge($array, $this->params['orderby']);

			} else {
				$this->params['orderby'] = $array;
			}
				
		}
		
	}
	
	function sortStringToArray($sting) {
		
		$sorts = explode(',', $string);
		$sort_array = array();
		
		foreach ($sorts as $sort) {
			
			if (strpos($sort, '-')) {
				$column = substr($sort, 0, -1);
				$order = '-';
			} elseif (strpos($sort, '+')) {
				$column = substr($sort, 0, -1);
				$order = '+';
			} else {
				$column = $sort;
				$order = '+';
			}
			
			$sort_array[$sort][0] = $column;
			$sort_array[$sort][1] = $order;
		}
		
		return $sort_array;
	}

	
	function setPage($value) {
		
		if (!empty($value)):
		
			$this->page = $value;
			
			if (!empty($this->pagination)):
				$this->pagination->setPage($value);
			endif;
			
		endif;
	}
	

	function getConstraints() {
	
		return $this->params['constraints'];
	}
	
	function setOffset($value) {
		
		if (!empty($value)):
			$this->params['offset'] = $value;
		endif;
	}
	
	function setFormat($value) {
		if (!empty($value)):
			$this->params['result_format'] = $value;
		endif;
	}
	
	function setPeriod($value) {
		if (!empty($value)):
			$this->params['period'] = $value;
		endif;
	}
	
	function setTimePeriod($period_name = '', $startDate = null, $endDate = null, $startTime = null, $endTime = null) {
	
		if (!$period_name) {
			
			if ($startDate && $endDate) {
				$period_name = 'date_range';
				$map = array('startDate' => $startDate, 'endDate' => $endDate);
			} elseif ($startDate && $endDate) {
				$period_name = 'time_range';
				$map = array('startTime' => $startTime, 'endTime' => $endTime);
			} else {
				$this->debug('no period params passed to owa_metric::setTimePeriod');
				return false;
			}
		}
		
		$p = owa_coreAPI::supportClassFactory('base', 'timePeriod');
		
		$p->set($period_name, $map);
		
		$this->setPeriod($p);
	}
	
	function makeTimePeriod($period = '') {
		
		$start = $this->params['period']->startDate->get($this->time_period_constraint_format);
		$end = $this->params['period']->endDate->get($this->time_period_constraint_format);
		$this->params['constraints'][$this->time_period_constraint_format] = array('operator' => 'BETWEEN', 'value' => array('start' => $start, 'end' => $end));

		return;
		
	}
	
	function setStartDate($date) {
		if (!empty($date)):
			$this->params['startDate'] = $date;
		endif;
	}
	
	function setEndDate($date) {
		if (!empty($date)):
			$this->params['endDate'] = $date;
		endif;
	}

	
		
	/**
	 * Retrieve Result data for a particular metric
	 * @depricated
	 * @param 	array $params
	 * @return 	array $data
	 * @access 	public
	 */
	function get_metric($params) {
	
		$m = owa_metric::get_instance($params['metric_package'], $params);	
		$data = $m->generate($params);
	
		switch ($params['result_format']) {
			case 'a_array':
				return $data;
			case 'inverted_array':
				return $data;
			default:
				return $data;
		}
		
		return $data;
	}
	
	/**
	 * @depricated
	 */
	function generate($method = 'calculate') {
		
		$this->makeTimePeriod();
		
		$this->db->multiWhere($this->getConstraints());
				
		if (!empty($this->pagination)):
			$this->pagination->setLimit($this->limit);
		endif;
		
		// pass limit to db object if one exists
		if (!empty($this->limit)):
			$this->db->limit($this->limit);
		endif;
		
		// pass order to db object if one exists
		
		
		
		// pagination
		if (!empty($this->page)):
			$this->pagination->setPage($this->page);
			$offset = $this->pagination->calculateOffset();
			$this->db->offset($offset);
		endif;
	
		
		$results = $this->$method();
		
		if (!empty($this->pagination)):
			$this->pagination->countResults($results);
		endif;
		
		return $results;
	
	}
	
	/**
	 * @depricated
	 */
	function generateResults() {
		
		// set period specific constraints
		$this->makeTimePeriod();
		// set constraints
		$this->db->multiWhere($this->getConstraints());
		// sets metric specific SQL
		$this->calculate();
		// generate paginated result set
		$rs = owa_coreAPI::supportClassFactory('base', 'paginatedResultSet');
		// pass limit to db object if one exists
		if (!empty($this->limit)) {
			$rs->setLimit($this->limit);
		}
		
		// pass limit to db object if one exists
		if (!empty($this->page)) {
			$rs->setPage($this->page);
		}
		
		// get results
		$rs->generate($this->db);
		
		// add labels
		$rs->setLabels($this->getLabels());
		
		// add period info
		$rs->setPeriodInfo($this->params['period']->getAllInfo());
		
		return $rs; 
	}
	
	/**
	 * Generates a result set for the metric
	 *
	 */
	function getResults() {
		
		// set period specific constraints
		$this->makeTimePeriod();
		// set constraints
		$this->db->multiWhere($this->getConstraints());
		// sets metric specific SQL
		//$this->calculate();
		
		// set selects
		$selects = $this->getSelect();
		foreach ($selects as $select) {
			
			$this->db->selectColumn($select[0], $select[1]);
		}
		
		$this->db->selectFrom($this->getTable());
		// get paginated result set object
		$rs = owa_coreAPI::supportClassFactory('base', 'paginatedResultSet');
		// generate aggregate results and merge into result set
		$results = $this->db->getOneRow();
		$rs->aggregates = array_merge($results, $rs->aggregates);
		
		// setup dimensional query
		if (!empty($this->dimensions)) {
			// apply dimensional SQL
			$this->applyDimensions();
			$this->db->selectFrom($this->getTable());
			// pass limit to db object if one exists
			if (!empty($this->limit)) {
				$rs->setLimit($this->limit);
			}
			// pass limit to db object if one exists
			if (!empty($this->page)) {
				$rs->setPage($this->page);
			}	
			// generate dimensonal results
			$rs->generate($this->db);
		}
		
		// add labels
		$rs->setLabels($this->getLabels());
		
		// add period info
		$rs->setPeriodInfo($this->params['period']->getAllInfo());
		
		return $rs;
	}
	
	/**
	 * @depricated
	 */
	function calculatePaginationCount() {
		
		if (method_exists($this, 'paginationCount')):
			$this->makeTimePeriod();
		
			$this->db->multiWhere($this->getConstraints());
		
			return $this->paginationCount();
		else:
			return false;
		endif;
	}
	
	/**
	 * Set the labels of the measures
	 *
	 */
	function setLabels($array) {
	
		$this->labels = $array;
		return;
	}
	
	/**
	 * Sets an individual label
	 * return the key so that it can be nested
	 * @return $key string
	 */
	function setLabel($key, $label) {
		
		$this->labels[$key] = $label;
		return $key;
	}
	
	/**
	 * Retrieve the labels of the measures
	 *
	 */
	function getLabels() {
	
		return $this->labels;
	
	}
	
	function getPagination() {
		
		$count = $this->calculatePaginationCount();
		$this->pagination->total_count = $count;
		return $this->pagination->getPagination(); 
	
	}
	
	function zeroFill(&$array) {
	
		// PHP 5 only function used here
		if (function_exists("array_walk_recursive")) {
			array_walk_recursive($array, array($this, 'addzero'));
		} else {
			owa_lib::array_walk_recursive($array, array(get_class($this).'Metric', 'addzero'));
		}
		
		return $array;
		
	}
	
	function addzero(&$v, $k) {
		
		if (empty($v)) {
			
			$v = 0;
			
		}
		
		return;
	}
	
	function getPeriod() {
	
		return $this->params['period'];
	}
	
	function getOrder() {
	
		if (array_key_exists('order', $this->params)) {
			return $this->params['order'];
		}
	}
	
	function getLimit() {
		
		return $this->limit;
		
	}
	
	/**
	 * Sets a dimension to use when calculating results
	 */
	function setDimension($dim) {
		
		if ($dim) {
			// add dimension
			$this->dimensions[] = $dim;
			//increment dimension count. not used yet.
			$this->dimensionCount++;
		}
	}
	
	function setDimensions($array) {
		
		if ($array) {
			$this->dimensions = array_merge($array, $this->dimensions);
			$this->dimensionCount = count($this->dimensions);
		}
		
	}
	
	function dimensionsStringToArray($string) {
		
		return explode(',', $string);
	}
	
	function dimensionsArrayToString($array) {
		
		return implode(',', $array);
	}
	
	/**
	 * Applies dimension sql to dao object
	 */
	function applyDimensions() {
		
		foreach ($this->dimensions as $dim) {
			
			// create tabl and column names
			list($table, $column) = explode('.', $dim);
			
			// check to see if dimension is denormalized
			if ($table === 'denorm') {
				// look up the real dimension from map
				$dim = $this->getDenormalizedDimension($column);
				
			} else {
				// add namespace table
				$nstable = $this->c->get('base', 'ns').$table;
				//create foreign key. assumes all fk's are table_id
				$fk = $table.'_id';
				
				// add join
				$this->db->join(OWA_SQL_JOIN_LEFT_OUTER, $nstable, $table, $fk);
			}
			
			// add column name to selct statement
			$this->db->selectColumn($dim);
			
			// add groupby
			$this->db->groupBy($dim);
			
		}
	}
	
	/**
	 * Sets a denormalized dimension
	 *
	 * Denormalized dimensions are looked up in this map by key
	 * and always begin with "denorm" (e.g. "denorm.key")
	 */
	function setDenormalizedDimension($name, $dim) {
	
		$this->denormalizedDimensions[$name] = $dim;
	}
	
	function getDenormalizedDimension($name) {
	
		if (array_key_exists($name, $this->denormalizedDimensions)) {
			return $this->denormalizedDimensions[$name];
		}
	}
	
	function setTable($name, $as) {
		
		$this->table = array($name, $as);
	}
	
	function getTable() {
		
		return $this->table;
	}
	
	function setSelect($column, $as) {
		
		$this->select[] = array($column, $as);
	}
	
	function getSelect() {
		
		return $this->select;
	}
	
	function mergeMetric($metric_obj) {
		
		if ($metric_obj->getTable() === $this->getTable()) {
			
			$this->select = array_merge($metric_obj->getSelect(), $this->select);
			return true;
		} else {
			return false;
		}
	}
}

?>