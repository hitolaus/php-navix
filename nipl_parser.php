<?php
require_once('http.php');

$nipl_parser_debug = 0;

class NiplParser {
	private $data   = "";
	private $offset = 0;
	
	private $headers = array();	
	
	private $url;
	private $v;
	
	private $in_if = false;
	private $in_else = false;
	private $select_if = false;
	
	public function __construct($proc = "", $url="") {
		$this->data = file_get_contents($proc . "?url=" . urlencode($url));
		$this->url = $url;
	}
	
	public function parse() {
		$e = new NiplElement($this->url);

		while (true) {
			$line = $this->next_line();
			if ($line === false) {
				break;
			}
		
			if ($this->should_ignore($line)) {
				// Ignore
			}
			elseif ($this->is_operator($line)) {
				$this->process_operator($e, $line);
			}
			elseif ($this->is_command($line)) {
				$done = $this->process_command($e, $line);
				if ($done) {
					return array($e->url, $e->s_cookie);
				}
			}
			else {
				$this->process_variable($e, $line);
			}
		}
		
		return array($e->url, $e->s_cookie);
	}
	
	private function process_command($e, $line) {
	
		if (!$this->should_execute()) {
			return;
		}
	
		if ($line == "scrape") {
			list($this->headers, $this->v) = $e->process();
			
			// Reset for further processing
			//$e = new NiplElement($this->url);
		}
		elseif (strncmp($line, "replace", 7) == 0) {
			list($cmd, $var, $val) = explode($line, " ");
			preg_replace($e->preg(), $val, $$var);
		}
		elseif (strncmp($line, "match", 5) == 0) {
			$var = substr($line, 6);

			preg_match($e->preg(), $e->$var, $this->v);
		}
		elseif (strncmp($line, "concat", 6) == 0) {
			list($cmd, $var, $name) = preg_split("/\s+/", $line, 3);

			$e->$var = $e->$var . $this->resolve_vars($e, $name);
		}
		elseif (strncmp($line, "play", 4) == 0) {
			return true;
		}
		elseif (strncmp($line, "print", 5) == 0) {
			// Ignore
		}
		
		return false;
	}
	
	private function process_operator($e, $line) {
		if (strncmp($line, "if", 2) == 0) {
			$this->in_if = true;	
			
			list($_if, $stmt) = explode(" ", $line, 2);
			
			preg_match("/(.*?)(\(<|>|<=|>=|=|==|!=|<>\))(.*)/", $stmt, $res);
			
			if (count($res) != 4) {
				$eval_stmt = $this->resolve_vars($e, $stmt);

				$select_if = !empty($eval_stmt);
				return;
			}
			
			$lhs = $res[1];
			$op  = $res[2];
			$rhs = $res[3];
			
			$this->select_if = $this->boolean_eval($e, $lhs, $op, $rhs);
		}
		elseif (strncmp($line, "else", 4) == 0) {
			$this->in_else = true;
		}
		elseif (strncmp($line, "endif", 5) == 0) {
			$this->in_if = false;
			$this->in_else = false;
		}
	}
	
	private function boolean_eval($e, $lhs, $op, $rhs) {

		$eval_lhs = $this->resolve_vars($e, $lhs);
		$eval_rhs = $this->resolve_vars($e, $rhs);

		if ($op == "=") $op = "==";
		if ($op == "<>") $op = "!=";

		eval("\$res = '".$eval_lhs."' " . $op . " '" .$eval_rhs. "';");

		//echo "[".$eval_lhs . "] [" . $op . "] [" . $eval_rhs . "] evaluated: [".$res."]<br/>";

		return $res;
	}
	
	private function should_execute() {
		return (!$this->in_if && !$this->in_else) ||
			   ($this->in_if && $this->select_if) ||
			   ($this->in_else && !$this->select_if);
	}
	
	private function process_variable($e, $line) {
	
		if (!$this->should_execute()) {
			return;
		}

		list($name, $value) = explode("=", $line, 2);
		
		if (strncmp($value, '\'', 1) ==0) {
			$value = substr($value, 1);
		}
		else {
			$value = $this->resolve_vars($e, $value);
		}
		
		if ($nipl_parser_debug)
			echo "DEBUG: " . $name. "=" . $value . "<br/>";
		
		$e->$name = $value;
	}
	
	
	private function is_command($line) {
		return preg_match("/^[^ ]+=.*/", $line) == 0;
	}
	
		private function should_ignore($line) {
		return empty($line) || 
			   strncmp($line, "#", 1) == 0 ||
			   strncmp($line, "//", 2) == 0;
	}
	
	private function is_operator($line) {
		return strncmp($line, "if", 2) == 0 ||
			   strncmp($line, "endif", 5) == 0 ||
			   strncmp($line, "else", 4) == 0;
	}
	
	private function next_line() {
		$i = strpos($this->data, "\n", $this->offset);

		if ($i === false) {
			return false;
		}
		
		$line = substr($this->data, $this->offset, $i - $this->offset);

		$this->offset = $i + 1;

		return trim($line);
	}
	
	private function resolve_vars($e, $name) {
		// Resolve v1,v2...
		preg_match("/v(\d+)/", $name, $res);

		if (count($res) == 2) {
			return $this->v[$res[1]];
		}
		
		if (!empty($e->$name)) {
			return $e->$name;
		}
		
		if (strncmp($name, "cookies.", 8) == 0) {
			$cookie_name = substr($name, 8);

			return get_cookie_value($this->headers, $cookie_name);
		}
		
		if (strncmp($name, "headers.", 8) == 0) {
			$header_name = substr($name, 8);
			return get_header_value($this->headers, $header_name);
		}
		
		if (strncmp($name, "'", 1) == 0) {
			return substr($name, 1);
		}
		
		return $name;
	}
}

class NiplElement {
	public $version = "v2";
	
	public $s_url = "";
	public $s_cookie = "";
	public $s_method = "GET";
	public $s_referer = "";
	public $s_postdata = "";
	public $s_agent = "";
	public $s_action = "";
	
	public $regex = "";
	public $url = "";
	
	public $htmRaw = "";
	
	public function __construct($url = "") {
		$this->s_url = $url;
	}
	
	public function process() {
		$method = strtoupper($this->s_method);

		$headers = array();
		if (!empty($this->s_cookie)) {
			array_push($headers, "Cookie: ".$this->s_cookie);
		}
		if (!empty($this->s_referer)) {
			array_push($headers, "Referer: ".$this->s_referer);
		}
		
		list($content, $response_headers) = http_request($this->s_url, $method, $this->s_postdata, $headers);

		$this->htmRaw = $content;

		preg_match($this->preg(), $content, $v);
		
		return array($response_headers, $v);
	}
	
	public function preg() {
		$regex = preg_replace("/\//", "\/", $this->regex);
		return "/".$regex."/";
	}
}

/*
$p = new NiplParser("http://navix.turner3d.net/proc/movshare", "http://www.movshare.net/video/kjb2zkraposh9");
list($url, $cookies) = $p->parse();

echo "<br/>Resolved URL: " . $url . "<br/><br>";
*/
/*
$p = new NiplParser("http://navix.turner3d.net/sproc/putlocker", "http://www.putlocker.com/file/BC2D1E994EEEEF9F");
list($url, $cookies) = $p->parse();

echo "<br/>Resolved URL: " . $url . "<br/><br/>";
*/
/*
$p = new NiplParser("http://navix.turner3d.net/sproc/veehd", "http://veehd.com/video/3906007_next-day-air");
list($url, $cookies) = $p->parse();

echo "<br/>Resolved URL: " . $url . "<br/><br/>";
*/
/*
$p = new NiplParser("http://navix.turner3d.net/sproc/sockshare", "http://www.sockshare.com/file/1SLYLENTF7R40KC");
list($url, $cookies) = $p->parse();

echo "<br/>Resolved URL: " . $url . "<br/>";
echo "Cookies: " . $cookies . "<br/><br/>";
*/
?>