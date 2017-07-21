<?php
/**
 * Created by PhpStorm.
 * User: Vea
 * Date: 2017/07/17 017
 * Time: 13:51
 */

class o2 implements \ArrayAccess, \Countable, \IteratorAggregate{
	protected $data=[];
	protected $_hasSet=false;
	//Countable
	public function count(){ return count($this->data); } //->count($this)
	//IteratorAggregate
	public function getIterator(){ return new ArrayIterator($this->data); } //foreach($this as ..)
	//ArrayAccess
	public function offsetSet($offset, $value){$this->data[$offset]=$value;}        //$this['xx'] ='xx'
	public function &offsetGet($offset){ return $this->data[$offset]; }                //=$this['zz']
	public function offsetExists($offset){ return isset($this->data[$offset]); }        //isset($this['xx']
	public function offsetUnset($offset){ unset($this->data[$offset]); }                //unset($this['xx']
	//php5.2+?
	public function __toString(){ return !empty($this->data) ?json_encode($this->data, JSON_UNESCAPED_UNICODE) :''; } //echo $this
	public function __debugInfo(){ return $this->data; }
}

class fileRead{
	private $hd=null;
	private $line='';
	private $lineRead=0;
	function __construct($file, $mode='r'){
		if(!$this->hd=fopen($file, $mode)) throw new RuntimeException('Couldn\'t open file "'.$file.'"');
	}
	function __destruct(){
		if(is_resource($this->hd)) fclose($this->hd);
	}
	function getLine($skip=1){
		if(0 === $skip) return $this->line;
		while($skip-- && false !== $this->line){
			$this->line=fgets($this->hd);
			$this->lineRead++;
		}
		if(false === $this->line){
			$this->lineRead--;
			return false;
		}
		$this->line=trim($this->line);
		return $this->line;
	}
}

class parser{
	/**
	 * @var fileRead
	 */
	private $fr;
	/**
	 * @var o2;
	 */
	public $profiler=[];
	
	private $files =[];
	private $functions =[];
	private $nodes =[];
	
	function __construct($fr){
		$this->fr =$fr;
	}
	function getLine(){
		return $this->fr->getLine();
	}
	private function checkNode($file, $fun){
		foreach($this->nodes as $_key =>$_node){
			if($_node['file'] ===$file && $_node['fun'] ===$fun){
				return $_key;
			}
		}
		return false;
	}
	private function checkNNewNode($file, $fun){
		$key =$this->checkNode($file, $fun);
		if(false ===$key){
			$this->nodes[] =['file'=>$file, 'fun'=>$fun];
			end($this->nodes);
			$key =key($this->nodes);
		}
		return $key;
	}
	function exec(){
		$line ='';
		while(true){
			$line=$this->getLine();
			if(empty(trim($line))) continue;
			@list($name, $value)=explode(': ', $line);
			$find=substr($line, 0, 3);
			if($find === 'fl=') break;
			$this->profiler[$name]=$value;
		}
		$last_idx =0;
		while(false !==$line){
			if(substr($line, 0, 3) ==='fl='){
				$node =[];
				if('(' ===$line[3]){
					preg_match('/^fl\=\((\d+)\)\s*(.*)$/', $line, $matches);
					if(isset($matches[2]) && !empty($matches[2])){
						$this->files[$matches[1]] =$matches[2];
						$node['file'] =$this->files[$matches[1]];
					}elseif(isset($matches[1]) && !empty($matches[1])){
						$node['file'] =$this->files[$matches[1]];
					}
				} else {
					list(,$file) =explode('=', $line);
					$node['file'] =$file;
				}

				$line =$this->getLine();
				if('(' ===$line[3]){
					preg_match('/^fn\=\((\d+)\)\s*(.*)$/', $line, $matches);
					if(isset($matches[2]) && !empty($matches[2])){
						$this->functions[$matches[1]]=$matches[2];
						$node['fun']=$this->functions[$matches[1]];
					}
					elseif(isset($matches[1]) && !empty($matches[1])){
						$node['fun']=$this->functions[$matches[1]];
					}

					$idx =$matches[1];
				} else {
					list(,$fun) =explode('=', $line);
					$node['fun'] =$fun;

					$idx =$this->checkNNewNode($node['file'], $node['fun']);
				}

				$node['title'] =$node['fun'];

				$line =$this->getLine();
				if(empty($line)){// main
					$line =$this->getLine();//summary
					@list($name, $value)=explode(': ', $line);
					$this->profiler[$name] =(int)$value;

					$this->getLine(1);
					$line =$this->getLine();
					@list($li, $self) =explode(' ', $line);
					$node['line'] =(int)$li;
					$node['self'] =(int)$self;

					$node['start'] =0;
					$node['cumulative'] =(int)$value;
				}else{
					@list($li, $self) =explode(' ', $line);
					$node['line'] =(int)$li;
					$node['self'] =(int)$self;
				}

				$this->nodes[$idx] =$node;
				$last_idx =$idx;
			} elseif(substr($line, 0, 4) ==='cfl='){
				if('('!==$line[4]){
					list(,$_file) =explode('=', $line);
				}
				$line =$this->getLine();
				if('(' ===$line[4]){
					preg_match('/^cfn\=\((\d+)\)/', $line, $matches);
					$cnode=$this->nodes[$matches[1]];
					$key=$matches[1];
				} else {
					list(,$_fun) =explode('=', $line);
					$key =$this->checkNode($_file, $_fun);
					$cnode =$this->nodes[$key];
				}

				if(empty($this->nodes[$last_idx]['children'])) $this->nodes[$last_idx]['children'] =[];

				$this->getLine(1);//calls
				$line =$this->getLine();
				@list($call, $cumulative) =explode(' ', $line);
				$cnode['call'] =(int)$call;
				$cnode['cumulative'] =(int)$cumulative+1;
				$this->nodes[$key] =$cnode;
				$this->nodes[$last_idx]['children'][] =$cnode;
			}
			$line =$this->getLine();
		}
		//$this->profiler['node'] =end($this->nodes);
		//$this->profiler['nodes'] =$this->nodes;
		$node =end($this->nodes);
		$nodes =[];
		$this->profiler['node'] =$this->_fixData($node, 0, 0, $nodes);
		//$this->profiler['nodes'] =$nodes;

		return $this;
	}
	function _fixData($node, $start=0, $lv=0, &$nodes){
		$node['start'] =$start;
		$node['lv'] =$lv;
		$nodes[] =$node;
		if(!empty($node['children'])){
			$_start =$start+$node['self'];
			foreach($node['children'] as $key=>$child){
				$node['children'][$key] =$this->_fixData($child, $_start, $lv+1, $nodes);
				$_start +=$child['cumulative'];
			}
		}
		return $node;
	}
	function __toString(){
		$json =json_encode($this->profiler, JSON_UNESCAPED_UNICODE);
		if(false ===$json){
			$json =json_last_error_msg();
		}
		return $json;
	}
}

