<?php

namespace Blink;

use Blink\Exception\InvalidURL;
use Blink\Exception\CodingError;
use JsonSerializable;

/**
 * URL.php
 * 
 * URL Object that wrap the php's `parse_url()` function
 * 
 * @author    Belikhun
 * @since     1.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */
class URL implements JsonSerializable {
    /**
     * Scheme, ex.: http, https
     * 
     * @var string
     */
    protected string $scheme = "";

    /**
     * Hostname part of the URL.
     * 
     * @var string
     */
    protected string $host = "";

    /**
     * Port number, empty means default 80 or 443 in case of http.
     * 
     * @var int
     */
    protected int $port = 0;

    /**
     * Username for http auth.
     * 
     * @var string
     */
    protected string $user = "";

    /**
     * Password for http auth.
     * 
     * @var string
     */
    protected $pass = "";

    /**
     * Script path.
     * 
     * @var string
     */
    protected $path = "";

    /**
     * Url parameters as associative array.
     * 
     * @var string[]
     */
    public $params = array();

	/**
	 * Construct a new url with params.
	 *
	 * @param	string|URL			$url
	 * @param	string[]|object		$params
	 */
	public function __construct($url, $params = []) {
		if ($url instanceof URL) {
            $this -> scheme = $url -> scheme;
            $this -> host = $url -> host;
            $this -> port = $url -> port;
            $this -> user = $url -> user;
            $this -> pass = $url -> pass;
            $this -> path = $url -> path;
            $this -> params = $url -> params;
			return;
        }

		$parts = parse_url($url);

		if ($parts === false)
			throw new InvalidURL($url);

		// Parse the query first.
		if (isset($parts["query"])) {
			parse_str(str_replace("&amp;", "&", $parts["query"]), $this -> params);
			unset($parts["query"]);
		}

		foreach ($parts as $key => $value)
			$this -> $key = $value;

		if (empty($this -> host))
			$this -> host = Server::$HOST;

		if (empty($this -> scheme))
			$this -> scheme = Server::$SCHEME;

		$this -> params($params);
	}

    /**
     * Add an array of params to the params for this url.
     *
     * @param	array           $params     Defaults to null. If null then returns all params.
     * @return	static
     * @throws	CodingError
     */
    public function params(array $params = null) {
        $params = (array) $params;

        foreach ($params as $key => $value)
            $this -> param($key, $value);

        return $this;
    }

    /**
     * Set a param value by param name.
     *
     * @param	string          $key		Param key name
     * @param	string          $value		Param value
     * @return	static
     * @throws	CodingError
     */
    public function param(string $key, $value) {
        if (is_int($key))
            throw new CodingError("Url parameters can not have numeric keys!");

        if (!is_string($value)) {
            if (is_array($value))
                throw new CodingError("Url parameters values can not be arrays!");
            
            if (is_object($value) and !method_exists($value, "__toString"))
                throw new CodingError("Url parameters values can not be objects, unless __toString() is defined!");
        }

        $this -> params[$key] = (string) $value;

        return $this;
    }

    /**
     * Remove all params if no arguments passed.
     * Remove selected params if arguments are passed.
     *
     * Can be called as either `remove_params("param1", "param2")`
     * or `remove_params(array("param1", "param2"))`.
     *
     * @param	string[]|string $params
     * @return	static
     */
    public function removeParams($params = null) {
        if (!is_array($params))
            $params = func_get_args();
        
        foreach ($params as $param)
            unset($this -> params[$param]);
		
        return $this;
    }

    /**
     * Get the params as as a query string.
     *
     * This method should not be used outside of this method.
     *
     * @param   bool    $escaped   Use &amp; as params separator instead of plain &
     * @return  string  Query string that can be added to a url.
     */
    public function getQuery($escaped = true) {
        $array = array();
        $params = $this -> params;

        foreach ($params as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $index => $value)
                    $array[] = rawurlencode($key . "[".$index."]") . "=" . rawurlencode($value);
            } else {
                if (isset($val) && $val !== "")
                    $array[] = rawurlencode($key) . "=" . rawurlencode($val);
                else
                    $array[] = rawurlencode($key);
            }
        }

        return ($escaped)
			? implode("&amp;", $array)
			: implode("&", $array);
    }

    public function __toString() {
        return $this -> out(true);
    }

    /**
     * Output URL string.
     *
     * If you use the returned URL in HTML code, you want the escaped ampersands. If you use
     * the returned URL in HTTP headers, you want `$escaped = false`.
     *
     * @param   bool    $escaped    Use &amp; as params separator instead of plain &
     * @return  string  Resulting URL string
     */
    public function out($escaped = true) {
        $uri = $this -> uri();

        $query = $this -> getQuery($escaped);
        if ($query !== "")
            $uri .= "?$query";

        return $uri;
    }

    /**
     * Returns url without parameters, everything before "?".
     * @return	string
     */
    public function uri() {
        $uri = $this -> scheme
			? "{$this -> scheme}:" . ((strtolower($this -> scheme) === "mailto") ? "" : "//")
			: "";

        $uri .= $this -> user
			? $this -> user . ($this->pass ? ":{$this->pass}" : "") . "@"
			: "";

        $uri .= $this -> host ? $this -> host : "";
        $uri .= $this -> port ? ":{$this->port}" : "";
        $uri .= $this -> path ? $this -> path : "";

        return $uri;
    }

    /**
     * Return host name part of the url.
     * 
     * @return string
     */
    public function getHost(): string {
        return $this -> host;
    }

    /**
     * Return URL scheme, ex.: http, https, ftp
     * 
     * @return string
     */
    public function getScheme(): string {
        return $this -> scheme;
    }

    /**
     * Return host's target port.
     * 
     * @return string
     */
    public function getPort(): int {
        return $this -> port;
    }

    /**
     * Return URL request path.
     * 
     * @return string
     */
    public function getPath(): string {
        return $this -> path;
    }

    /**
     * Sets the scheme for the URI (the bit before ://)
     * 
     * @param	string	$scheme
     * @return  static
     */
    public function setScheme(string $scheme) {
        // See http://www.ietf.org/rfc/rfc3986.txt part 3.1.
        if (preg_match("/^[a-zA-Z][a-zA-Z0-9+.-]*$/", $scheme)) {
            $this -> scheme = $scheme;
        } else {
            throw new CodingError("Bad URL scheme: \"$scheme\"");
        }

        return $this;
    }

    public function jsonSerialize(): mixed {
        return $this -> out(false);
    }
}
