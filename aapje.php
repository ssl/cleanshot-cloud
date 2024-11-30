<?php
/**
 * aapje.php
 * 
 * A lightweight, single-file PHP framework for building simple APIs.
 * 
 * https://github.com/ssl/aapje.php
 * Version: 0.2
 * License: MIT
 * 
 * The MIT License (MIT)
 * 
 * Copyright (c) 2024 Elyesa (aapje.php)
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software
 * and associated documentation files (the "Software"), to deal in the Software without restriction,
 * including without limitation the rights to use, copy, modify, merge, publish, distribute,
 * sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * 
 * See the full license text at https://opensource.org/licenses/MIT
 */

class aapje {
    public static $version = '0.2';
    private static $routes = [];
    private static $dbConfig = [];
    private static $pdo = null;
    private static $request = null;
    private static $response = null;
    private static $config = [
        'default_headers' => []
    ];

    /**
     * Define a route with a specific HTTP method or all methods using '*'.
     *
     * @param string $method  HTTP method (e.g., 'GET', 'POST', '*')
     * @param string $pattern URL pattern with optional parameters (e.g., '/user/@id')
     * @param callable $callback Function to execute when the route matches
     */
    public static function route(string $method, string $pattern, callable $callback) {
        self::$routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'callback' => $callback
        ];
    }

    /**
     * Access the request object.
     *
     * @return Request
     */
    public static function request() {
        if (self::$request === null) {
            self::$request = new Request();
        }
        return self::$request;
    }

    /**
     * Access the response object.
     *
     * @return Response
     */
    public static function response() {
        if (self::$response === null) {
            self::$response = new Response();
            // Apply default headers if any
            foreach (self::$config['default_headers'] as $key => $value) {
                self::$response->header($key, $value);
            }
        }
        return self::$response;
    }

    /**
     * Set global configuration options.
     *
     * @param array $config Configuration array (e.g., ['default_headers' => ['X-Custom-Header' => 'Value']])
     */
    public static function setConfig(array $config) {
        foreach ($config as $key => $value) {
            if ($key === 'default_headers' && is_array($value)) {
                self::$config['default_headers'] = array_merge(self::$config['default_headers'], $value);
                if (self::$response !== null) {
                    foreach ($value as $headerKey => $headerValue) {
                        self::$response->header($headerKey, $headerValue);
                    }
                }
            }
        }
    }

    /**
     * Start processing the incoming request.
     */
    public static function run() {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        try {
            $routeFound = false;
            foreach (self::$routes as $route) {
                if ($route['method'] === '*' || $route['method'] === strtoupper($requestMethod)) {
                    $pattern = preg_replace('/@([\w]+)/', '(?P<$1>[^/]+)', $route['pattern']);
                    $pattern = '#^' . $pattern . '$#';
                    if (preg_match($pattern, $requestUri, $matches)) {
                        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                        call_user_func_array($route['callback'], $params);
                        return;
                    }
                    $routeFound = true;
                }
            }
            if ($routeFound) {
                self::response()->statusCode(405)->echo(['error' => 'Method Not Allowed']);
            } else {
                self::response()->statusCode(404)->echo(['error' => 'Not Found']);
            }
        } catch (Exception $e) {
            self::response()->statusCode(500)->echo(['error' => $e->getMessage()]);
        }
    }

    /**
     * Set database configuration parameters.
     *
     * @param array $config Database configuration (host, dbname, user, password)
     */
    public static function setDbConfig(array $config) {
        self::$dbConfig = $config;
    }

    /**
     * Establish a database connection using PDO.
     */
    private static function connectDb() {
        if (self::$pdo === null) {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=utf8mb4",
                self::$dbConfig['host'],
                self::$dbConfig['dbname']
            );
            self::$pdo = new PDO($dsn, self::$dbConfig['user'], self::$dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
    }

    /**
     * Execute a raw SQL query with optional parameters.
     *
     * @param string $query  SQL query with placeholders
     * @param array $params  Parameters to bind to the query
     * @return PDOStatement
     */
    public static function query(string $query, array $params = []) {
        self::connectDb();
        $stmt = self::$pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Validate query options to prevent SQL injection via identifiers.
     *
     * @param array $options Query options (table, columns, orderBy, limit, sort)
     * @throws Exception if validation fails
     */
    private static function checkQuery(array $options) {
        foreach (['table', 'columns', 'orderBy'] as $key) {
            if (isset($options[$key])) {
                if ($key === 'columns' && $options[$key] === '*') {
                    continue; // Allow '*'
                }
                $identifiers = is_array($options[$key]) ? $options[$key] : [$options[$key]];
                foreach ($identifiers as $identifier) {
                    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $identifier)) {
                        throw new Exception("Invalid identifier: {$identifier}");
                    }
                }
            }
        }
        if (isset($options['limit']) && (!is_int($options['limit']) || $options['limit'] <= 0)) {
            throw new Exception("Invalid limit: {$options['limit']}");
        }
        if (isset($options['sort']) && !in_array(strtoupper($options['sort']), ['ASC', 'DESC'], true)) {
            throw new Exception("Invalid sort: {$options['sort']}");
        }
    }

    /**
     * Insert a new record into a table.
     *
     * @param string $table Table name
     * @param array $data   Associative array of column-value pairs
     * @return string      Last inserted ID
     * @throws Exception   If query fails
     */
    public static function insert(string $table, array $data) {
        self::checkQuery(['table' => $table, 'columns' => array_keys($data)]);
        $keys = implode(',', array_map(function($key) { return "$key"; }, array_keys($data)));
        $placeholders = implode(',', array_fill(0, count($data), '?'));
        $query = "INSERT INTO $table ($keys) VALUES ($placeholders)";
        self::query($query, array_values($data));
        return self::$pdo->lastInsertId();
    }

    /**
     * Update existing records in a table.
     *
     * @param string $table      Table name
     * @param array $data        Associative array of column-value pairs to update
     * @param array $conditions  Associative array of conditions for the WHERE clause
     * @throws Exception         If query fails
     */
    public static function update(string $table, array $data, array $conditions = []) {
        self::checkQuery(['table' => $table, 'columns' => array_keys($data)]);
        $set = implode(',', array_map(function($key) { return "$key = ?"; }, array_keys($data)));

        $params = array_values($data);

        if (!empty($conditions)) {
            $wheres = [];
            foreach ($conditions as $key => $value) {
                self::checkQuery(['columns' => $key]);
                $wheres[] = "$key = ?";
                $params[] = $value;
            }
            $whereClause = implode(' AND ', $wheres);
            $query = "UPDATE $table SET $set WHERE $whereClause";
        } else {
            $query = "UPDATE $table SET $set";
        }

        self::query($query, $params);
    }

    /**
     * Delete records from a table.
     *
     * @param string $table      Table name
     * @param array $conditions  Associative array of conditions for the WHERE clause
     * @throws Exception         If query fails
     */
    public static function delete(string $table, array $conditions = []) {
        self::checkQuery(['table' => $table]);
        $query = "DELETE FROM $table";

        $params = [];
        if (!empty($conditions)) {
            $wheres = [];
            foreach ($conditions as $key => $value) {
                self::checkQuery(['columns' => $key]);
                $wheres[] = "$key = ?";
                $params[] = $value;
            }
            $whereClause = implode(' AND ', $wheres);
            $query .= " WHERE $whereClause";
        }

        self::query($query, $params);
    }

    /**
     * Select a single record from a table.
     *
     * @param string       $table      Table name
     * @param string|array $columns    Columns to select ('*' or array of column names)
     * @param array        $conditions Associative array of conditions for the WHERE clause
     * @param array        $options    Additional options ('orderBy', 'sort')
     * @return array|false             Single record as an associative array or false if not found
     * @throws Exception               If query fails
     */
    public static function select(string $table, $columns = '*', array $conditions = [], array $options = []) {
        if ($columns !== '*') {
            if (!is_array($columns)) {
                throw new Exception("Columns must be '*' or an array of columns");
            }
            self::checkQuery(['table' => $table, 'columns' => $columns]);
            $cols = implode(',', array_map(function($col) { return "$col"; }, $columns));
        } else {
            $cols = '*';
        }
        self::checkQuery([
            'table' => $table,
            'orderBy' => $options['orderBy'] ?? null,
            'sort' => $options['sort'] ?? null,
        ]);
        $query = "SELECT $cols FROM $table";

        $params = [];
        if (!empty($conditions)) {
            $wheres = [];
            foreach ($conditions as $key => $value) {
                self::checkQuery(['columns' => $key]);
                $wheres[] = "$key = ?";
                $params[] = $value;
            }
            $whereClause = implode(' AND ', $wheres);
            $query .= " WHERE $whereClause";
        }

        if (isset($options['orderBy'])) {
            $orderBy = $options['orderBy'];
            $query .= " ORDER BY $orderBy";
            if (isset($options['sort'])) {
                $query .= " " . strtoupper($options['sort']);
            }
        }

        $query .= " LIMIT 1";

        $stmt = self::query($query, $params);
        return $stmt->fetch();
    }

    /**
     * Select multiple records from a table.
     *
     * @param string       $table      Table name
     * @param string|array $columns    Columns to select ('*' or array of column names)
     * @param array        $conditions Associative array of conditions for the WHERE clause
     * @param array        $options    Additional options ('orderBy', 'sort', 'limit')
     * @return array                   Array of records
     * @throws Exception               If query fails
     */
    public static function selectAll(string $table, $columns = '*', array $conditions = [], array $options = []) {
        if ($columns !== '*') {
            if (!is_array($columns)) {
                throw new Exception("Columns must be '*' or an array of columns");
            }
            self::checkQuery(['table' => $table, 'columns' => $columns]);
            $cols = implode(',', array_map(function($col) { return "$col"; }, $columns));
        } else {
            $cols = '*';
        }
        self::checkQuery([
            'table' => $table,
            'orderBy' => $options['orderBy'] ?? null,
            'sort' => $options['sort'] ?? null,
            'limit' => $options['limit'] ?? null,
        ]);
        $query = "SELECT $cols FROM $table";

        $params = [];
        if (!empty($conditions)) {
            $wheres = [];
            foreach ($conditions as $key => $value) {
                self::checkQuery(['columns' => $key]);
                $wheres[] = "$key = ?";
                $params[] = $value;
            }
            $whereClause = implode(' AND ', $wheres);
            $query .= " WHERE $whereClause";
        }

        if (isset($options['orderBy'])) {
            $orderBy = $options['orderBy'];
            $query .= " ORDER BY $orderBy";
            if (isset($options['sort'])) {
                $query .= " " . strtoupper($options['sort']);
            }
        }

        if (isset($options['limit'])) {
            $query .= " LIMIT " . intval($options['limit']);
        }

        $stmt = self::query($query, $params);
        return $stmt->fetchAll();
    }
}

// Request class
class Request {
    /**
     * Retrieve a specific HTTP request header.
     *
     * @param string $key Header name
     * @return string|null
     */
    public function header(string $key): ?string {
        $header = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $_SERVER[$header] ?? null;
    }

    /**
     * Retrieve all HTTP request headers.
     *
     * @return array
     */
    public function headers(): array {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'HTTP_') {
                $header = str_replace(
                    ' ',
                    '-',
                    ucwords(strtolower(str_replace('_', ' ', substr($key, 5))))
                );
                $headers[$header] = $value;
            }
        }
        return $headers;
    }

    /**
     * Retrieve a specific cookie value.
     *
     * @param string $key Cookie name
     * @return string|null
     */
    public function cookie(string $key): ?string {
        return $_COOKIE[$key] ?? null;
    }

    /**
     * Retrieve all cookies.
     *
     * @return array
     */
    public function cookies(): array {
        return $_COOKIE;
    }

    /**
     * Retrieve information about an uploaded file.
     *
     * @param string $key File input name
     * @return array|null
     */
    public function file(string $key) {
        return $_FILES[$key] ?? null;
    }

    /**
     * Retrieve all uploaded files.
     *
     * @return array
     */
    public function files(): array {
        return $_FILES;
    }

    public function input($decode = true) {
        $input = file_get_contents('php://input');
        if ($decode) {
            return json_decode($input, true);
        } else {
            return $input;
        }
    }

    /**
     * Retrieve a specific GET parameter.
     *
     * @param string $key Parameter name
     * @return string|null
     */
    public function getParam(string $key): ?string {
        return $_GET[$key] ?? null;
    }

    /**
     * Retrieve all GET parameters.
     *
     * @return array
     */
    public function getParams(): array {
        return $_GET;
    }

    /**
     * Retrieve a specific POST parameter.
     *
     * @param string $key Parameter name
     * @return string|null
     */
    public function postParam(string $key): ?string {
        return $_POST[$key] ?? null;
    }

    /**
     * Retrieve all POST parameters.
     *
     * @return array
     */
    public function postParams(): array {
        return $_POST;
    }

    /**
     * Retrieve the client's IP address.
     *
     * @return string
     */
    public function ip(): string {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Retrieve the client's User-Agent string.
     *
     * @return string|null
     */
    public function userAgent(): ?string {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }
}

// Response class
class Response {
    private $headers = ['Content-Type' => 'application/json'];
    private $statusCode = 200;

    /**
     * Set a single HTTP response header.
     *
     * @param string $key Header name
     * @param string $value Header value
     * @return self
     */
    public function header(string $key, string $value): self {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Set multiple HTTP response headers.
     *
     * @param array $headers Associative array of headers
     * @return self
     */
    public function headers(array $headers): self {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Set a cookie.
     *
     * @param string $name Cookie name
     * @param string $value Cookie value
     * @param array $options Additional options (e.g., 'expires', 'path')
     * @return self
     */
    public function cookie(string $name, string $value, array $options = []): self {
        setcookie($name, $value, $options);
        return $this;
    }

    /**
     * Set multiple cookies.
     *
     * @param array $cookies Associative array of cookies
     * @return self
     */
    public function cookies(array $cookies): self {
        foreach ($cookies as $name => $data) {
            $value = $data['value'] ?? '';
            $options = $data['options'] ?? [];
            setcookie($name, $value, $options);
        }
        return $this;
    }

    /**
     * Set the HTTP status code.
     *
     * @param int $code HTTP status code
     * @return self
     */
    public function statusCode(int $code): self {
        $this->statusCode = $code;
        http_response_code($code);
        return $this;
    }

    /**
     * Send the response to the client.
     *
     * @param mixed $content Content to send (array or string)
     * @param bool $json Whether to JSON-encode the content if it's an array
     */
    public function echo($content, $json = true) {
        foreach ($this->headers as $key => $value) {
            header("$key: $value");
        }
        if (is_string($content) && $json) {
            $content = ['echo' => $content];
        }
        echo is_array($content) ? json_encode($content) : $content;
        exit;
    }
}
