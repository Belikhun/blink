<?php

namespace Blink;

use Blink\Exception\MissingParam;
use Blink\Router\Route;

/**
 * Request.php
 * 
 * Represent a request.
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class Request {
	public array $args = array();

	public array $params = array();

	public array $data = array();
	public array $headers = array();
	public array $cookies = array();

	/**
	 * List of uploaded files.
	 * @var UploadedFile[]
	 */
	public array $files = array();

	public string $method;

	public string $path;

	public ?Route $route = null;

	public function __construct(
		string $method,
		string $path,
		array $args,
		array $params,
		array $data,
		array $headers,
		array $cookies,
		array $files
	) {
		$this -> method = $method;
		$this -> path = $path;
		$this -> args = $args;
		$this -> params = $params;
		$this -> data = $data;
		$this -> cookies = $cookies;
		$this -> headers = array_change_key_case($headers, CASE_LOWER);

		foreach ($files as $key => $file)
			$this -> files[$key] = new UploadedFile($file);
	}

	/**
	 * Return an uploaded file from current request.
	 * @param	string	$name
	 * @return	?UploadedFile
	 */
	public function file(string $name) {
		if (isset($this -> files[$name]))
			return $this -> files[$name];
		
		return null;
	}

	public function arg(string $name, $type = TYPE_TEXT, $default = null) {
		if (isset($this -> args[$name]))
			return cleanParam($this -> args[$name], $type);
		
		return $default;
	}

	public function header(string $name, $type = TYPE_TEXT, $default = null) {
		$name = strtolower($name);

		if (isset($this -> headers[$name]))
			return cleanParam($this -> headers[$name], $type);
		
		return $default;
	}

	public function cookie(string $name, $default = null) {
		if (isset($this -> cookies[$name]))
			return $this -> cookies[$name];
		
		return $default;
	}

	public function param(string $name, $type = TYPE_TEXT, $default = null) {
		if (isset($this -> data[$name]))
			$param = $this -> data[$name];
		else if (isset($this -> params[$name]))
			$param = $this -> params[$name];
		else
			return $default;

		return cleanParam($param, $type);
	}

	public function requiredParam(string $name, $type = TYPE_TEXT) {
		$param = $this -> param($name, $type);

		if ($param === null)
			throw new MissingParam($name);
	
		return $param;
	}

	public function get(string $name, $type = TYPE_TEXT, $default = null) {
		return $this -> arg($name, $type)
			|| $this -> param($name, $type)
			|| $default;
	}

	public function accept(string $mimetype) {
		$accept = $this -> header("Accept", TYPE_RAW);

		if (empty($accept))
			return false;

		list($accept) = explode(";", $accept);
		return (strcasecmp($accept, $mimetype) === 0);
	}

	public function json(bool $assoc = false) {
		$json = file_get_contents("php://input");
		return safeJSONParsing($json, "[request:body]", $assoc);
	}
}
