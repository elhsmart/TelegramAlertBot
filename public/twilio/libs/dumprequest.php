<?php

ini_set("allow_url_fopen", true);

class DumpHTTPRequestToFile {
	public function execute($targetFile) {
		$data = sprintf(
			"%s %s %s\n\nHTTP headers:\n",
			$_SERVER['REQUEST_METHOD'],
			$_SERVER['REQUEST_URI'],
			$_SERVER['SERVER_PROTOCOL']
		);
		foreach ($this->getHeaderList() as $name => $value) {
			$data .= $name . ': ' . $value . "\n";
		}
        $data .= "\nRequest body:\n";
        $data .= file_get_contents('php://input') . "\n";

        $data .= "\nGET vars:\n";
        $data .= implode("\n", $_GET) . "\n";

        $data .= "\nPOST vars:\n";
        $data .= implode("\n", array_map(
            function ($v, $k) { return sprintf("%s='%s'", $k, $v); },
            $_POST,
            array_keys($_POST)
        )) . "\n\n";
		file_put_contents(
			$targetFile,
			$data
        );    
	}
	private function getHeaderList() {
		$headerList = [];
		foreach ($_SERVER as $name => $value) {
			if (preg_match('/^HTTP_/',$name)) {
				// convert HTTP_HEADER_NAME to Header-Name
				$name = strtr(substr($name,5),'_',' ');
				$name = ucwords(strtolower($name));
				$name = strtr($name,' ','-');
				// add to list
				$headerList[$name] = $value;
			}
		}
		return $headerList;
	}
}
