<?php
class SynoDLMSearchT411 {
    private $domain = 't411.ai';
    private $dlurl = 'https://127.0.0.1/t411.syno.php'; // torrent download url
    private $aurl; //auth url
    private $qurl; // search url
    private $purl; // torrent page url

    // go throw all results
    private $limit = 100;

    // for auth
    private $uid = 0;
    private $token = 0;

    public $debug  = 0;

    public function __construct() {
        // Init properties.
        $apiUrl = sprintf('https://api.%s', $this->domain);
        $siteUrl = sprintf('https://www.%s', $this->domain);
        $this->aurl = $apiUrl . '/auth'; //auth url
        $this->qurl = $apiUrl . '/torrents/search/'; // search url
        $this->dlurl = 'https://127.0.0.1/t411.syno.php'; // torrent download url
        $this->purl = $siteUrl . '/torrents/'; // torrent page url
    }

    private function DebugLog($str) {
        if ($this->debug==1) {
            echo $str . "\n";
        }
    }

    private function auth($username, $password) {
        $this->DebugLog('auth:');
        // get auth variables
        $auth = 'username=' . $username . '&password=' . $password;

        // query with curl
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->aurl);
        curl_setopt($ch,CURLOPT_POST, 2);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $auth);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 

        $body = curl_exec($ch);

        curl_close($ch);

        // now, process the JSON string
        $json = json_decode($body);
        $this->DebugLog('    body:' . $body);

        if ( isset($json->error) ) {
            $this->DebugLog('    error: ' . $json->error);
            return false;
        }

        $this->token = $json->token;
        $this->uid = $json->uid;

        $this->DebugLog('    token: ' . $this->token);
        $this->DebugLog('    DONE');
        return true;
    }

    public function VerifyAccount($username, $password) {
        $this->DebugLog('VerifyAccount:');
        $this->DebugLog('   username: '.$username);
        $this->DebugLog('   password: '.$password);

        $this->DebugLog('    DONE');
        return $this->auth($username, $password);
    }


    public function prepare($curl, $query, $username, $password) { 
        $this->DebugLog('prepare:');
        $this->DebugLog('   username: '.$username);
        $this->DebugLog('   username: '.$username);
        $this->DebugLog('   query: '.$query);
        
        $url = $this->qurl . urlencode($query) . '?offset=0&limit=' . $this->limit;
        $this->DebugLog('    url: ' . $url);

        $this->auth($username, $password);

        curl_setopt($curl, CURLOPT_HTTPHEADER, array ('Authorization: '. $this->token) );
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $this->DebugLog('    DONE');
    }

    public function parse($plugin, $response) {
        $this->DebugLog('parse:');

        $json = json_decode($response);

        $this->DebugLog('   count: '.$json->total);

        // parse the results
        $this->addPlugin($plugin, $json);

        // get the other pages of results
        for ($i = $this->limit; $i < $json->total; $i += $this->limit) {
            // new curl
            $curl = curl_init();

            // set curl options
            $url = $this->qurl . $json->query . '?offset=' . $i . '&limit=' . $this->limit;
            $this->DebugLog('   url: '.$url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array ('Authorization: '. $this->token) );
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

            // query
            $jsonPage = json_decode(curl_exec($curl));
            curl_close($curl);

            // parse the results
            $this->addPlugin($plugin, $jsonPage);
        }

        $this->DebugLog('    DONE');

        return $json->total;
    }

    private function addPlugin($plugin, $json) {
        // parse the results
        foreach ($json->torrents as $value) {
            // $this->DebugLog('    name: ' . $value->name);
            $plugin->addResult(
                $value->name, //title
                $this->dlurl . '?torrentid=' . $value->id . '&token=' . $this->token, //download link
                $value->size, //size
                $value->added, //datetime, format 2010-12-30 13:20:10
                $this->purl . $value->rewritename, //torrent page
                $value->id, //hash, can be empty, must be unique or results are merged, its the unique key of the list
                $value->seeders, // seeds
                $value->leechers, // leechs
                $value->categoryname // category
                );
        }
    }
}
