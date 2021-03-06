<?php

/**
 * I don't believe in license
 * You can do want you want with this program
 * - gwen -
 */

class AutoKnoxss
{
	private $knoxss = null;

	private $burp_source;
	private $url_source;
	private $single_source;
	
	private $max_error = 0;
	private $max_throttle = 0;
	private $min_throttle = 0;
	
	private $n_child = 0;
	private $max_child = 3;
	private $sleep = 100000;
	private $t_process = [];
	private $t_signal_queue = [];

	private $t_requests = [];

	
	public function __construct() {
		$this->knoxss = new KnoxssRequest();
	}
	
	
	public function getUserAgent() {
		return $this->knoxss->getUserAgent();
	}
	public function setUserAgent( $v ) {
		return $this->knoxss->setUserAgent( trim($v) );
	}

	public function getCookies() {
		return $this->knoxss->getCookies();
	}
	public function setCookies( $v ) {
		return $this->knoxss->setCookies( trim($v) );
	}
	
	public function getVerbosity() {
		return $this->knoxss->getVerbosity();
	}
	public function setVerbosity( $v ) {
		return $this->knoxss->setVerbosity( (int)$v );
	}
	
	public function getTimeout() {
		return $this->knoxss->getTimeout();
	}
	public function setTimeout( $v ) {
		return $this->knoxss->setTimeout( (int)$v );
	}

	public function getWPnonce() {
		return $this->knoxss->WPnonce();
	}
	public function setWPnonce( $v ) {
		return $this->knoxss->setWPnonce( trim($v) );
	}

	public function disableColor() {
		return $this->knoxss->disableColor();
	}


	public function getBurpSource() {
		return $this->burp_source;
	}
	public function setBurpSource( $v ) {
		$f = trim( $v );
		if( !is_file($f) ) {
			return false;
		}
		$this->burp_source = $f;
		return true;
	}

	
	public function getMaxError() {
		return $this->max_error;
	}
	public function setMaxError( $v ) {
		$this->max_error = (int)$v;
		return true;
	}
	

	public function getMinThrottle() {
		return $this->min_throttle;
	}
	public function setMinThrottle( $v ) {
		$this->min_throttle = (int)$v;
		return true;
	}

	
	public function getMaxThrottle() {
		return $this->max_throttle;
	}
	public function setMaxThrottle( $v ) {
		$this->max_throttle = (int)$v;
		return true;
	}

	
	public function getMaxChild() {
		return $this->max_child;
	}
	public function setMaxChild( $v ) {
		$this->max_child = (int)$v;
		return true;
	}

	
	public function getSingleSource() {
		return $this->single_source;
	}
	public function setSingleSource( $v ) {
		$this->single_source = trim( $v );
		return true;
	}
	
	
	public function getUrlSource() {
		return $this->url_source;
	}
	public function setUrlSource( $v ) {
		$f = trim( $v );
		if( !is_file($f) ) {
			return false;
		}
		$this->url_source = $f;
		return true;
	}

	
	// http://stackoverflow.com/questions/16238510/pcntl-fork-results-in-defunct-parent-process
	// Thousand Thanks!
	public function signal_handler( $signal, $pid=null, $status=null )
	{
		// If no pid is provided, Let's wait to figure out which child process ended
		if( !$pid ){
			$pid = pcntl_waitpid( -1, $status, WNOHANG );
		}
		
		// Get all exited children
		while( $pid > 0 )
		{
			if( $pid && isset($this->t_process[$pid]) ) {
				// I don't care about exit status right now.
				//  $exitCode = pcntl_wexitstatus($status);
				//  if($exitCode != 0){
				//      echo "$pid exited with status ".$exitCode."\n";
				//  }
				// Process is finished, so remove it from the list.
				$this->n_child--;
				unset( $this->t_process[$pid] );
			}
			elseif( $pid ) {
				// Job finished before the parent process could record it as launched.
				// Store it to handle when the parent process is ready
				$this->t_signal_queue[$pid] = $status;
			}
			
			$pid = pcntl_waitpid( -1, $status, WNOHANG );
		}
		
		return true;
	}
	
	
	private function loadBurp()
	{
		$this->t_requests = BurpRequest::loadDatas( $this->burp_source );
		if( !$this->t_requests ) {
			Utils::help( 'File source is not XML, not loaded' );
		}
		echo 'XML loaded: '.$this->burp_source."\n";
		return count( $this->t_requests );
	}
	
	
	private function loadUrls()
	{
		$this->t_requests = UrlRequest::loadDatas( $this->url_source );
		if( !$this->t_requests ) {
			Utils::help( 'File source not loaded' );
		}
		echo 'Urls loaded: '.$this->url_source."\n";
		return count( $this->t_requests );
	}

	
	private function loadSingle()
	{
		$this->t_requests = SingleRequest::loadDatas( $this->single_source );
		return count( $this->t_requests );
	}
	

	public function loadDatas()
	{
		if( $this->burp_source ) {
			return $this->loadBurp();
		} elseif( $this->url_source ) {
			return $this->loadUrls();
		}  elseif( $this->single_source ) {
			return $this->loadSingle();
		} else {
			return false;
		}

		echo "\nLoading ".count($this->t_requests)." requests...\n";
		
		$t_keys = [];
		foreach( $this->t_requests as $k=>$br ) {
			if( !in_array($br->key,$t_keys) ) {
				$t_keys[] = $br->key;
			} else {
				unset( $this->t_requests[$k] );
			}
		}
		sort( $this->t_requests );
	}
	
	
	public function init()
	{
		if( !$this->knoxss->wpnonce ) {
			$this->knoxss->extractNonce();
		}
		if( !$this->knoxss->wpnonce ) {
			Utils::help( 'WPNonce not found' );
		}
		echo "WPnonce extracted: ".$this->knoxss->getWPnonce()."\n";

		$n_request = count( $this->t_requests );
		echo 'Testing '.$n_request." request...\n\n";

		$t_splitted = [];
		for( $i=0,$d=0 ; $i<$n_request ; $i++,$d++ ) {
			$t_splitted[$d%$this->max_child][] = $this->t_requests[$i];
		}
		$this->t_requests = $t_splitted;
		//var_dump($this->t_requests);
		
		foreach( $t_splitted as $k=>$tbr ) {
			echo 'Process '.($k+1).' will treat '.count($tbr)." requests\n";
		}
		echo "\n";
		
		posix_setsid();
		declare( ticks=1 );
		pcntl_signal( SIGCHLD, array($this,'signal_handler') );
	}
	
	
	public function run()
	{
		$max_child = count( $this->t_requests );
		
		for( $current_pointer=0 ; $current_pointer<$max_child ; )
		{
			if( $this->n_child < $this->max_child )
			{
				$pid = pcntl_fork();
		
				if( $pid == -1 ) {
					// fork error
				} elseif( $pid ) {
					// father
					$this->n_child++;
					$current_pointer++;
					$this->t_process[$pid] = uniqid();
			        if( isset($this->t_signal_queue[$pid]) ){
			        	$this->signal_handler( SIGCHLD, $pid, $this->t_signal_queue[$pid] );
			        	unset( $this->t_signal_queue[$pid] );
			        }
				} else {
					// child process
					$this->loop( $current_pointer );	
					exit( 0 );
				}
			}
		}
		
		while( $this->n_child ) {
			// surely leave the loop please :)
			//echo date("d/m/Y H:i:s")."\n";
			sleep( 1 );
		}
		
		echo "\n";
	}
	
	
	private function loop( $current_pointer )
	{
		$i = $n_error = 0;
		$child_id = $current_pointer + 1;
		
		foreach( $this->t_requests[$current_pointer] as $r )
		{
			$i++;
			$this->knoxss->target = $r->url;
			$this->knoxss->post = $r->post;
			$this->knoxss->setChildId( $child_id );
			$this->knoxss->setRequestId( $i );

			ob_start();
			$xss = $this->knoxss->go();
			$this->knoxss->result();
			$buffer = ob_get_contents();
			ob_end_clean();
			echo $buffer;

			if( $xss >= 0 ) {
				$n_error = 0;
			} else {
				$n_error++;
			}
			if( $this->max_error > 0 && $n_error >= $this->max_error ) {
				Utils::_println( '['.$child_id.'] Too many errors, contact the vendor or try later!', 'light_cyan' );
				break;
			}
			
			usleep( rand($this->min_throttle,$this->max_throttle) );
		}
		
		return $xss;
	}
}
