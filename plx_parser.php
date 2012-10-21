<?php
require_once('strings.php');

class PlxParser {

	private $data   = "";
	private $offset = 0;

	public $version = 1;
	public $title   = "";
	public $background = "";

	public function __construct($url="") {
		if (!empty($url)) {
			$this->data = file_get_contents($url);
			$this->process_header();
		}
	}
	
	private function process_header() {
		while(true) {
			list($line, $i) = $this->next_line();

			if ($line === false) {
				break;
			}

			if (!$this->is_processable_line($line)) {
				return;
			}
		
			$line = $this->parse_codes($line);
	
			list($name, $value) = explode('=', $line, 2);
			
			if ($this->is_new_element($name)) {
				return;
			}

			// Actually move the offset
			$this->offset = $i;

			if ($name == "version") {
				$this->version = $value;
			}
			
			if ($name == "title") {
				$this->title = $value;
			}
			
			if ($name == "background") {
				$this->background = $value;
			}
		}
	}
	
	private function parse_codes($line) {
		return preg_replace('/\[\/?COLOR.*?\]/', '', $line);
	}
	
	private function is_processable_line($line) {
		if (strpos($line, '=') === false) {
			return false;
		}
		
		$trim_line = trim($line);
		
		return strncmp($trim_line, '#', 1) != 0;
	}
	
	private function is_new_element($name) {
		return $name == 'type';
	}
	
	private function next_line() {
		if ($this->offset < 0 || $this->offset >= strlen($this->data)) {
			return array(false,-1);
		}
	
		$i = strpos($this->data, "\n", $this->offset);

		if ($i === false) {
			$i = strlen($this->data);
		}
		
		$line = substr($this->data, $this->offset, $i - $this->offset);

		++$i;

		return array($line,$i);
	}
	
	public function next() {
		if ($this->offset >= strlen($this->data)) {
			return NULL;
		}
	
		$e = new PlxElement();
		
		list($type_line, $i) = $this->next_line();
		if ($type_line === false) {
			return NULL;
		}
		$this->offset = $i;
		
		list($name, $value) = explode('=', $type_line, 2);
		$e->type = $value;

		$in_desc = false;
	
		while(true) {
			list($line, $i) = $this->next_line();

			if ($line === false) {
				break;
			}

			if (!$this->is_processable_line($line)) {
				$this->offset = $i;
				continue;
			}
	
			$line = $this->parse_codes($line);
	
			list($name, $value) = explode('=', $line, 2);
			
			if ($this->is_new_element($name)) {
				break;
			}
		
			$this->offset = $i;

			if ($in_desc) {
				if (endsWith($line, "/description")) {
					$end_idx = strrpos($line, "/description");
					$e->description .= "<br/>".substr($line, 0, $end_idx);	
					$in_desc = false;
				}
				else {
					$e->description .= "<br/>".$line;
				}
				continue;
			}

			if ($name != "description") {
				$in_desc = false;
			}

			if ($name == "name") {
				$e->name = $value;
			}
			if ($name == "URL") {
				$e->url = $value;
			}
			if ($name == "description") {
				$e->description = $value;
				if (endsWith($value, "/description")) {
					$end_idx = strrpos($value, "/description");
					$e->description = substr($value, 0, $end_idx);					
					$in_desc = false;
				}
				else {
					$in_desc = true;
				}
			}
			if ($name == "rating") {
				$e->rating = $value;
			}
			if ($name == "date") {
				$e->date = $value;
			}
			if ($name == "thumb") {
				$e->thumb = $value;
			}
			if ($name == "player") {
				$e->player = $value;
			}
			if ($name == "processor") {
				$e->processor = $value;
			}
		}
		return $e;
	}
}

class PlxElement {
	public $type = "";
	public $name = "";
	public $thumb = "";
	public $date  = "";
	public $rating = -1;
	public $url = "";
	public $description = "";
	public $player = "";
	public $processor = "";
}

// Test
/*
//$p = new PlxParser('http://navix.turner3d.net/playlist/week.plx');
$p = new PlxParser('http://navix.turner3d.net/playlist/42993/movies.plx');
echo "v: " . $p->version . "<br/>b: " . $p->background . "<br/>t: " . $p->title . "<br/>"; 
$e = $p->next();
while(isset($e)) {
	echo $e->description . "<br/><br/>";
	$e = $p->next();
}
*/
?>