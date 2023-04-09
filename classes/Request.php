<?php
/**
 * Request.php
 * 
 * File Description
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

namespace Blink;
use Blink\Exception\MissingParam;
use Blink\Router\Route;

class Request {
	public Array $args = Array();

	public Array $params = Array();

	public Array $data = Array();
	public Array $headers = Array();
	public Array $cookies = Array();

	/**
	 * List of uploaded files.
	 * @var UploadedFile[]
	 */
	public Array $files = Array();

	public String $method;

	public String $path;

	public Route $route;

	public function __construct(
		Route $route,
		String $method,
		String $path,
		Array $args,
		Array $params,
		Array $data,
		Array $headers,
		Array $cookies,
		Array $files
	) {
		$this -> route = $route;
		$this -> method = $method;
		$this -> path = $path;
		$this -> args = $args;
		$this -> params = $params;
		$this -> data = $data;
		$this -> headers = $headers;
		$this -> cookies = $cookies;

		foreach ($files as $key => $file)
			$this -> files[$key] = new UploadedFile($file);
	}

	/**
	 * Return an uploaded file from current request.
	 * @param	string	$name
	 * @return	?UploadedFile
	 */
	public function file(String $name) {
		if (isset($this -> files[$name]))
			return $this -> files[$name];
		
		return null;
	}

	public function arg(String $name, $type = TYPE_TEXT, $default = null) {
		if (isset($this -> args[$name]))
			return cleanParam($this -> args[$name], $type);
		
		return $default;
	}

	public function header(String $name, $type = TYPE_TEXT, $default = null) {
		if (isset($this -> headers[$name]))
			return cleanParam($this -> headers[$name], $type);
		
		return $default;
	}

	public function cookie(String $name, $default = null) {
		if (isset($this -> cookies[$name]))
			return $this -> cookies[$name];
		
		return $default;
	}

	public function param(String $name, $type = TYPE_TEXT, $default = null) {
		if (isset($this -> data[$name]))
			$param = $this -> data[$name];
		else if (isset($this -> params[$name]))
			$param = $this -> params[$name];
		else
			return $default;

		return cleanParam($param, $type);
	}

	public function requiredParam(String $name, $type = TYPE_TEXT) {
		$param = $this -> param($name, $type);

		if ($param === null)
			throw new MissingParam($name);
	
		return $param;
	}

	public function get(String $name, $type = TYPE_TEXT, $default = null) {
		return $this -> arg($name, $type)
			|| $this -> param($name, $type)
			|| $default;
	}
}
