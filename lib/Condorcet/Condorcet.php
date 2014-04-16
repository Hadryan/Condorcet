<?php
/*
	Condorcet PHP Class, with Schulze Methods and others !

	Version : 0.7

	By Julien Boudry - MIT LICENSE (Please read LICENSE.txt)
	https://github.com/julien-boudry/Condorcet_Schulze-PHP_Class 
*/

namespace Condorcet ;


// Include Algorithms
foreach (glob( __DIR__ . DIRECTORY_SEPARATOR."algorithms".DIRECTORY_SEPARATOR."*.algo.php" ) as $Condorcet_filename)
{
	include_once $Condorcet_filename ;
}

// Set the default Condorcet Class algorithm
Condorcet::setClassMethod('Schulze') ;


// Base Condorcet class
class Condorcet
{

/////////// CLASS ///////////


	protected static $_version		= '0.7' ;	

	protected static $_class_method	= null ;
	protected static $_auth_methods	= '' ;

	protected static $_force_method	= false ;
	protected static $_show_error	= true ;

	const LENGTH_OPION_ID = 10 ;

	// Return library version numer
	public static function getClassVersion ()
	{
		return self::$_version ;
	}

	// Return an array with auth methods
	public static function getAuthMethods ()
	{
		$auth = explode(',', self::$_auth_methods) ;

		return $auth ;
	}

	// Check if the method is supported
	public static function isAuthMethod ($method)
	{
		$auth = self::getAuthMethods() ;

		if ( in_array($method,$auth, true) )
			{ return true ;	}
		else
			{ return false ; }
	}


	// Add algos
	public static function addAlgos ($algos)
	{
		if ( is_null($algos) )
			{ return false ; }

		elseif ( is_string($algos) && !self::isAuthMethod($algos) )
		{
			if ( !self::test_algos($algos) )
			{
				return false ;
			}

			if ( empty(self::$_auth_methods) )
				{ self::$_auth_methods .= $algos ; }
			else
				{ self::$_auth_methods .= ','.$algos ; }
		}

		elseif ( is_array($algos) )
		{
			foreach ($algos as $value)
			{
				if ( !self::test_algos($value) )
				{
					return false ;
				}

				if ( self::isAuthMethod($value) )
					{ continue; }

				if ( empty(self::$_auth_methods) )
					{ self::$_auth_methods .= $value ; }
				else
					{ self::$_auth_methods .= ','.$value ; }
			}
		}
	}

		// Check if the class Algo. exist and ready to be used
		protected static function test_algos ($algos)
		{
			if ( !class_exists(__NAMESPACE__.'\\'.$algos) )
			{				
				self::error(9) ;
				return false ;
			}

			$tests_method = array ('getResult', 'get_stats') ;

			foreach ($tests_method as $method)
			{
				if ( !method_exists(__NAMESPACE__.'\\'.$algos , $method) )
				{
					self::error(10) ;
					return false ;
				}
			}

			return true ;
		}


	// Change default method for this class, if $force == true all current and further objects will be forced to use this method and will not be able to change it by themselves.
	public static function setClassMethod ($method, $force = false)
	{		
		if ( self::isAuthMethod($method) )
		{
			self::$_class_method = $method ;

			self::forceMethod($force);
		}
	}

			// if $force == true all current and further objects will be forced to use this method and will not be abble to change it by themselves.
			public static function forceMethod ($force = true)
			{
				if ($force)
				{
					self::$_force_method = true ;
				}
				else
				{
					self::$_force_method = false ;
				}
			}


	// Active trigger_error() - True by default
	public static function setError ($param = true)
	{
		if ($param)
		{
			self::$_show_error = true ;
		}
		else
		{
			self::$_show_error = false ;
		}
	}


	protected static function error ($code, $infos = null)
	{
		$error[1] = array('text'=>'Bad option format', 'level'=>E_USER_WARNING) ;
		$error[2] = array('text'=>'The voting process has already started', 'level'=>E_USER_WARNING) ;
		$error[3] = array('text'=>'This option ID is already registered', 'level'=>E_USER_NOTICE) ;
		$error[4] = array('This option ID do not exist'=>'', 'level'=>E_USER_WARNING) ;
		$error[5] = array('text'=>'Bad vote format', 'level'=>E_USER_WARNING) ;
		$error[6] = array('text'=>'You need to specify votes before results', 'level'=>E_USER_ERROR) ;
		$error[7] = array('text'=>'Your Option ID is too long > '.self::LENGTH_OPION_ID, 'level'=>E_USER_WARNING) ;
		$error[8] = array('text'=>'This method do not exist', 'level'=>E_USER_ERROR) ;
		$error[9] = array('text'=>'The algo class you want has not been defined', 'level'=>E_USER_ERROR) ;
		$error[10] = array('text'=>'The algo class you want is not correct', 'level'=>E_USER_ERROR) ;
		$error[11] = array('text'=>'You try to unserialize an object version older than your actual Class version. This is a problematic thing', 'level'=>E_USER_WARNING) ;

		
		if ( array_key_exists($code, $error) )
		{
			if ( self::$_show_error || $error[$code]['level'] < E_USER_WARNING )
			{
				trigger_error( $error[$code]['text'].' : '.$infos, $error[$code]['level'] );
			}
		}
		elseif (self::$_show_error)
		{
			trigger_error( 'Mysterious Error : '.$infos, E_USER_NOTICE );
		}

		return false ;
	}



/////////// CONSTRUCTOR ///////////


	// Data and global options
	protected $_method ;
	protected $_options ;
	protected $_votes ;

	// Mechanics 
	protected $_i_option_id	= 'A' ;
	protected $_vote_state	= 1 ; // 1 = Add Option / 2 = Voting / 3 = Some result have been computing
	protected $_options_count = 0 ;
	protected $_vote_tag = 0 ;
	protected $_ObjectVersion ;

	// Result
	protected $_Pairwise ;
	protected $_algos ;

		//////

	public function __construct ($method = null)
	{
		$this->_method = self::$_class_method ;

		$this->_options	= array() ;
		$this->_votes 	= array() ;

		$this->setMethod($method) ;

		// Store constructor version (security for caching)
		$this->_ObjectVersion = self::$_version ;
	}

		public function getObjectVersion ()
		{
			return $this->_ObjectVersion ;
		}

	public function __sleep ()
	{
		// Don't include computing data, only options & votes
		return array	(
			'_method',
			'_options',
			'_votes',
			'_i_option_id',
			'_vote_state',
			'_options_count',
			'_vote_tag',
			'_ObjectVersion'
						);
	}

	public function __wakeup ()
	{
		if ( version_compare($this->getObjectVersion(),self::getClassVersion(),'<') )
		{
			return self::error(11, 'Your object version is '.$this->getObjectVersion().' but the class engine version is '.self::getClassVersion());
		}

		if ($this->_vote_state > 2) 
			{$this->_vote_state = 2 ;}
	}

		//////

	// Change the object method, except if self::$_for_method == true
	public function setMethod ($method = null)
	{
		if (self::$_force_method)
		{
			$this->_method = self::$_class_method ;
		}
		elseif ( $method != null && self::isAuthMethod($method) )
		{
			$this->_method = $method ;
		}

		return $this->_method ;
	}


	// Return object state with options & votes input
	public function getConfig ()
	{
		$this->setMethod() ;

		return array 	(
							'object_method'		=> $this->getMethod(),
							'class_default_method'	=> self::$_class_method,
							'class_auth_methods'=> self::getAuthMethods(),
							'force_class_method'=> self::$_force_method,

							'class_show_error'	=> self::$_show_error,

							'object_state'		=> $this->_vote_state
						);
	}


	public function getMethod ()
	{
		return $this->setMethod() ;
	}


	// Reset all, be ready for a new vote - PREFER A CLEAN DESTRUCT of this object
	public function resetAll ()
	{
		$this->cleanupResult() ;

		$this->_options = null ;
		$this->_options_count = 0 ;
		$this->_votes = null ;
		$this->_i_option_id = 'A' ;
		$this->_vote_state	= 1 ;

		$this->setMethod() ;
	}



/////////// OPTIONS ///////////


	// Add a vote option before voting
	public function addOption ($option_id = null)
	{
		// only if the vote has not started
		if ( $this->_vote_state > 1 ) { return self::error(2) ; }
		
		// Filter
		if ( !is_null($option_id) && !ctype_alnum($option_id) && !is_int($option_id) )
			{ return self::error(1, $option_id) ; }
		if ( mb_strlen($option_id) > self::LENGTH_OPION_ID || is_bool($option_id) )
			{ return self::error(1, $option_id) ; }

		
		// Process
		if ( empty($option_id) ) // Option_id is empty ...
		{
			while ( !$this->try_addOption($this->_i_option_id) )
			{
				$this->_i_option_id++ ;
			}

			$this->_options[] = $this->_i_option_id ;
			$this->_options_count++ ;

			return $this->_i_option_id ;
		}
		else // Try to add the option_id
		{
			$option_id = trim($option_id);

			if ( $this->try_addOption($option_id) )
			{
				$this->_options[] = $option_id ;
				$this->_options_count++ ;

				return true ;
			}
			else
			{
				return self::error(3,$option_id) ;
			}
		}
	}

		protected function try_addOption ($option_id)
		{
			return !$this->existOptionId($option_id) ;
		}


	// Destroy a register vote option before voting
	public function removeOption ($list)
	{
		// only if the vote has not started
		if ( $this->_vote_state > 1 ) { return self::error(2) ; }

		
		if ( !is_array($list) )
		{
			$option_id	= array($option_id) ;
		}

		foreach ($list as $option_id)
		{
			$value = trim($option_id) ;

			$option_key = $this->getOptionKey($option_id) ;

			if ( $option_key === false )
				{ return self::error(4,$option_id) ; }

			unset($this->_options[$option_key]) ;
			$this->_options_count-- ;
		}
	}


		//:: OPTIONS TOOLS :://

		// Count registered options
		public function countOptions ()
		{
			return $this->_options_count ;
		}

		// Get the list of registered option
		public function getOptionsList ()
		{
			return $this->_options ;
		}

		protected function getOptionKey ($option_id)
		{
			return array_search($option_id, $this->_options, true) ;
		}

		protected function getOptionId ($option_key)
		{
			self::get_static_option_id($option_key, $this->_options) ;
		}

			public static function get_static_option_id ($option_key, &$options)
			{
				return $options[$option_key] ;
			}

		protected function existOptionId ($option_id)
		{
			return in_array($option_id, $this->_options) ;
		}



/////////// VOTING ///////////


	// Close the option config, be ready for voting (optional)
	public function close_options_config ()
	{
		if ( $this->_vote_state === 1 )
			{ 
				$this->_vote_state = 2 ;
			}

		// If voting continues after a first set of results
		elseif ( $this->_vote_state > 2 )
			{ 
				$this->cleanupResult();
			}

		return true ;
	}


	// Add a single vote. Array key is the rank, each option in a rank are separate by ',' It is not necessary to register the last rank.
	public function addVote ($vote, $tag = null)
	{
		$this->close_options_config() ;

			////////

		// Translate the string if needed
		if ( is_string($vote) )
		{
			$vote = $this->convert_vote_input($vote) ;
		}

		// Check array format
		if ( !is_array($vote) || !$this->checkVoteInput($vote) )
			{ return self::error(5) ; }

		// Check tag format
		if ( is_bool($tag) )
			{ return self::error(5) ; }

		// Sort
		ksort($vote);

		// Register vote
		return $this->registerVote($vote, $tag) ;
	}

		// From a string like 'A>B=C=H>G=T>Q'
		protected function convert_vote_input ($formula)
		{
			$vote = explode('>', $formula);

			foreach ($vote as $rank => $rank_vote)
			{
				$vote[$rank] = explode('=', $rank_vote);

				// Del space at start and end
				foreach ($vote[$rank] as $key => $value)
				{
					$vote[$rank][$key] = trim($value);
				}
			}

			return $vote ;
		}

		protected function checkVoteInput ($vote)
		{
			$list_option = array() ;

			if ( count($vote) > $this->_options_count || count($vote) < 1 )
				{ return false ; }

			foreach ($vote as $rank => $choice)
			{
				// Check key & option
				if ( !is_numeric($rank) || $rank > $this->_options_count || empty($choice) )
					{ return false ; }

					//////

				if (!is_array($choice))
				{
					$options = explode(',', $choice) ;
				}
				else
				{
					$options = $choice ;
				}

				foreach ($options as $option)
				{
					if ( !$this->existOptionId($option) )
					{
						return false ;
					}

					// Do not do 2x the same option
					if ( !in_array($option, $list_option)  )
						{ $list_option[] = $option ; }
					else 
						{ return false ; }
				}
			}

			return true ;
		}

		// Write a new vote
		protected function registerVote ($vote, $tag = null)
		{
			$last_line_check = array() ;
			$vote_r = array() ;

			$i = 1 ;
			foreach ($vote as $value)
			{
				if ( !is_array($value) )
				{
					$vote_r[$i] = explode(',', $value) ;
				}
				else
				{
					$vote_r[$i] = $value ;
				}

				// $last_line_check
				foreach ($vote_r[$i] as $option)
				{
					$last_line_check[] = $this->getOptionKey($option) ;
				}

				$i++ ;
			}

			if ( count($last_line_check) < count($this->_options) )
			{
				foreach ($this->_options as $key => $value)
				{
					if ( !in_array($key,$last_line_check) )
					{
						$vote_r[$i][] = $value ;
					}
				}
			}

			// Vote identifiant
			if ($tag !== null)
			{
				$vote_r['tag'] = explode(',',$tag) ;
			}
			
			$vote_r['tag'][] = $this->_vote_tag++ ;
			
			
			// Register
			$this->_votes[] = $vote_r ;

			return $vote_r['tag'] ;
		}


	public function removeVote ($tag, $with = true)
	{
		$this->close_options_config() ;

			//////

		foreach ($this->_votes as $key => $value)
		{					
			if ($with)
			{
				if (in_array($tag, $value['tag']))
				{
					unset($this->_votes[$key]) ;
				}
			}
			else
			{
				if (!in_array($tag, $value['tag']))
				{
					unset($this->_votes[$key]) ;
				}
			}
		}
	}


	//:: VOTING TOOLS :://

	// How many votes are registered ?
	public function countVotes ()
	{
		return count($this->_votes) ;
	}

	// Get the votes registered list
	public function getVotesList ($tag = null, $with = true)
	{
		if (empty($tag))
		{
			return $this->_votes ;
		}
		else
		{
			$search = array() ;

			foreach ($this->_votes as $key => $value)
			{					
				if ($with)
				{
					if (in_array($tag, $value['tag']))
					{
						$search[$key] = $value ;
					}
				}
				else
				{
					if (!in_array($tag, $value['tag']))
					{
						$search[$key] = $value ;
					}
				}
			}

			return $search ;
		}
	}



/////////// RETURN RESULT ///////////


	//:: PUBLIC FUNCTIONS :://


	// Generic function for default result with ability to change default object method
	public function getResult ($method = null)
	{
		// Method
		$this->setMethod() ;
		// Prepare
		$this->prepareResult() ;

			//////

		if ($method === null)
		{
			$this->initResult($this->_method) ;

			return $this->_algos[$this->_method]->getResult() ;
		}
		elseif (self::isAuthMethod($method))
		{
			$this->initResult($method) ;

			return $this->_algos[$method]->getResult() ;
		}
		else
		{
			return self::error(8,$method) ;
		}
	}


	public function getWinner ($substitution = false)
	{
		// Method
		$this->setMethod() ;
		// Prepare
		$this->prepareResult() ;

			//////

		if ( $substitution )
		{
			if ($substitution === true)
				{$substitution = $this->_method ;}

			if ( self::isAuthMethod($substitution) )
				{$algo = $substitution ;}
			else
				{return self::error(9,$substitution);}
		}
		else
			{$algo = 'Condorcet_Basic';}

			//////

		$this->initResult($algo) ;

		return $this->_algos[$algo]->getResult()[1] ;
	}


	public function getLoser ($substitution = false)
	{
		// Method
		$this->setMethod() ;
		// Prepare
		$this->prepareResult() ;

			//////

		if ( $substitution )
		{			
			if ($substitution === true)
				{$substitution = $this->_method ;}
			
			if ( self::isAuthMethod($substitution) )
				{$algo = $substitution ;}
			else
				{return self::error(9,$substitution);}
		}
		else
			{$algo = 'Condorcet_Basic';}

			//////

		$this->initResult($algo) ;

		$result = $this->_algos[$algo]->getResult() ;

		return $result[count($result)] ;
	}


	public function getResultStats ($method = null)
	{
		// Method
		$this->setMethod() ;
		// Prepare
		$this->prepareResult() ;

			//////

		if ($method === null)
		{
			$this->initResult($this->_method) ;

			return $this->_algos[$this->_method]->get_stats() ;
		}
		elseif (self::isAuthMethod($method))
		{
			$this->initResult($method) ;

			return $this->_algos[$method]->get_stats() ;
		}
		else
		{
			return self::error(8,$option_id) ;
		}
	}



	//:: TOOLS FOR RESULT PROCESS :://


	// Prepare to compute results & caching system
	protected function prepareResult ()
	{
		if ($this->_vote_state > 2)
		{
			return false ;
		}
		elseif ($this->_vote_state === 2)
		{
			$this->cleanupResult();

			// Do Pairewise
			$this->doPairwise() ;

			// Change state to result
			$this->_vote_state = 3 ;

			// Return
			return true ;
		}
		else
		{
			self::error(6) ;
			return false ;
		}
	}


	protected function initResult ($method)
	{
		if ( !isset($this->_algos[$method]) )
		{
			$param['_Pairwise'] = $this->_Pairwise ;
			$param['_options_count'] = $this->_options_count ;
			$param['_options'] = $this->_options ;
			$param['_votes'] = $this->_votes ;

			$class = __NAMESPACE__.'\\'.$method ;
			$this->_algos[$method] = new $class($param) ;
		}
	}


	// Cleanup results to compute again with new votes
	protected function cleanupResult ()
	{
		// Reset state
		if ($this->_vote_state > 2)
		{
			$this->_vote_state = 2 ;
		}

			//////

		// Clean pairwise
		$this->_Pairwise = null ;

		// Algos
		$this->_algos = null ;
	}


	//:: GET RAW DATA :://

	public function getPairwise ()
	{
		$this->prepareResult() ;

		return self::get_static_Pairwise ($this->_Pairwise, $this->_options) ;
	}

		public static function get_static_Pairwise (&$pairwise, &$options)
		{
			$explicit_pairwise = array() ;

			foreach ($pairwise as $candidate_key => $candidate_value)
			{
				$candidate_key = self::get_static_option_id($candidate_key, $options) ;
				
				foreach ($candidate_value as $mode => $mode_value)
				{
					foreach ($mode_value as $option_key => $option_value)
					{
						$explicit_pairwise[$candidate_key][$mode][self::get_static_option_id($option_key,$options)] = $option_value ;
					}
				}
			}

			return $explicit_pairwise ;
		}



/////////// PROCESS RESULT ///////////


	//:: COMPUTE PAIRWISE :://

	protected function doPairwise ()
	{		
		$this->_Pairwise = array() ;

		foreach ( $this->_options as $option_key => $option_id )
		{
			$this->_Pairwise[$option_key] = array( 'win' => array(), 'null' => array(), 'lose' => array() ) ;

			foreach ( $this->_options as $option_key_r => $option_id_r )
			{
				if ($option_key_r != $option_key)
				{
					$this->_Pairwise[$option_key]['win'][$option_key_r]		= 0 ;
					$this->_Pairwise[$option_key]['null'][$option_key_r]	= 0 ;
					$this->_Pairwise[$option_key]['lose'][$option_key_r]	= 0 ;
				}
			}
		}


		// Win && Null
		foreach ( $this->_votes as $vote_id => $vote_ranking )
		{
			// Del vote identifiant
			unset($vote_ranking['tag']) ;

			$done_options = array() ;

			foreach ($vote_ranking as $options_in_rank)
			{
				$options_in_rank_keys = array() ;

				foreach ($options_in_rank as $option)
				{
					$options_in_rank_keys[] = $this->getOptionKey($option) ;
				}

				foreach ($options_in_rank as $option)
				{
					$option_key = $this->getOptionKey($option);

					// Process
					foreach ( $this->_options as $g_option_key => $g_option_id )
					{
						// Win
						if (
								$option_key !== $g_option_key && 
								!in_array($g_option_key, $done_options, true) && 
								!in_array($g_option_key, $options_in_rank_keys, true)
							)
						{

							$this->_Pairwise[$option_key]['win'][$g_option_key]++ ;

							$done_options[] = $option_key ;
						}

						// Null
						if (
								$option_key !== $g_option_key &&
								count($options_in_rank) > 1 &&
								in_array($g_option_key, $options_in_rank_keys)
							)
						{
							$this->_Pairwise[$option_key]['null'][$g_option_key]++ ;
						}
					}
				}
			}
		}


		// Loose
		foreach ( $this->_Pairwise as $option_key => $option_results )
		{
			foreach ($option_results['win'] as $option_compare_key => $option_compare_value)
			{
				$this->_Pairwise[$option_key]['lose'][$option_compare_key] = $this->countVotes() -
						(
							$this->_Pairwise[$option_key]['win'][$option_compare_key] + 
							$this->_Pairwise[$option_key]['null'][$option_compare_key]
						) ;
			}
		}
	}
}