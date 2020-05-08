<?php
//error_reporting(0);

define("_HOST", "jbbs.shitaraba.net");
define("_INTERVAL", 1);

class Shitaraba {

    public $category, $address, $saveDir;
    private $setting;

    function __construct () {

    }

    function main () {

        global $argv;
        $this->isShitarabaURL($argv[1]);

        $this->setting = json_decode($this->getSetting($this->category, $this->address));
        $subject = $this->getSubjectTxt();

        $listData = $this->generateDatURLs($subject);

        print "ThreadCount: " . count($listData) . "\n";
        $this->download($listData);
        $this->exportSetting();

        $this->exportHeadTxt($this->getHeadTxt());
        $this->archiveZip();

        return;

    }

    function isShitarabaURL ($url) {

        if (preg_match("@https?://jbbs\.(shitaraba\.net|livedoor\.jp)/(.*?)/([0-9]{1,})/?@", $url, $matchs)) {

            $this->category = $matchs[2];
            $this->address = $matchs[3];
            $this->saveDir = "./" . $this->category . "_" . $this->address . "/";

            return true;
        }

        return false;

    }

    function getSetting ($category, $address) {
        
        $setting = file_get_contents("https://"._HOST."/bbs/api/setting.cgi/".$category."/".$address."/");

        if($setting == false)
            exit;

        $setting = mb_convert_encoding($setting, "UTF8", "EUC-JP");
        $json = $this->iniToKeyValue($setting);

        return $json;

    }

    function iniToKeyValue ($str) {

        $ini = explode("\n", $str);

        foreach ($ini as $line) {

            $delimiter_pos = strpos($line, "=");
            $key = substr($line, 0, $delimiter_pos);
            $value = substr($line, $delimiter_pos+1);

            if ($key == "")
                continue;

            $ret[$key] = $value;

        }

        return json_encode($ret);

    }

    function getSubjectTxt () {

        return file_get_contents("https://"._HOST."/".$this->category."/".$this->address."/subject.txt");

    }

    function getHeadTxt () {

        return file_get_contents("https://"._HOST."/".$this->category."/".$this->address."/head.txt");

    }

    function generateDatURLs ($str) {

        $list = explode("\n", $str);

        foreach ($list as $line) {

            if ($line == "") 
                continue;

            if (preg_match("/([0-9]{1,})\.cgi,(.*?)\(([0-9]{1,})\)/", $line, $matchs)) {

                $uniqueKey = sha1($matchs[1].$matchs[2]);

                $ret[$uniqueKey] = array(
                    "threadkey" => $matchs[1],
                    "title" => $matchs[2],
                    "count" => $matchs[3],
                    "datURL" => "https://"._HOST."/bbs/rawmode.cgi/".
                        $this->setting->DIR."/".$this->setting->BBS."/".$matchs[1]."/"
                );

            }

        }

        return $ret;

    }

    function showList ($listData) {

        foreach ($listData as $data) {

            print $data["datURL"]."\n";

        }

        return;

    }

    function download ($listData) {

        foreach ($listData as $data) {

            if ($this->getDat($data) != -1)
                sleep(_INTERVAL);

        }

        return;

    }

    private function getDat ($data) {

        $data["cache"] = $this->cacheStatus($data);
        
        print $data["datURL"];

        if ($data["cache"]) {

            print " ".str_pad($data["cache"]["count"], 4, " ", STR_PAD_LEFT)." / "
                . str_pad($data["count"], 4, " ", STR_PAD_LEFT) . " SKIP\n";

            if ($data["cache"]["count"] == $data["count"]) {
                return -1;
            } else if ($data["cache"]["count"] != $data["cache"]["last"]) {
                $data["cache"] = false;
            }
            
        } else {

            print " .... ";

        }

        $options = stream_context_create(
            array( 
                'http' => array( 
                    'timeout' => 10
                )
            )
        );

        $rawdat = file_get_contents($data["datURL"], 0, $options);

        if (!$data["cache"] && !$rawdat) {
            die("ERR timeout\n");
        }

        if (!is_dir($this->saveDir))
            mkdir($this->saveDir);

        if ($data["cache"]) {
            file_put_contents($this->saveDir . $data["threadkey"] . ".dat", $rawdat, FILE_APPEND);
        } else {
            file_put_contents($this->saveDir . $data["threadkey"] . ".dat", $rawdat);
        }

        print strlen($rawdat) . " bytes OK\n";

        return;

    }

    private function cacheStatus ($data) {

        if (file_exists($this->saveDir . $data["threadkey"].".dat")) {

            $datfile = file($this->saveDir . $data["threadkey"].".dat");

            $resCount = count($datfile);
            $lastLine = explode("<>", array_pop($datfile));

            return array("count" => $resCount, "last" => $lastLine[0]);

        } else {
            return false;

        }

    }

    function exportSetting () {

        file_put_contents($this->saveDir . "setting.json", json_encode($this->setting, JSON_UNESCAPED_UNICODE));

        return;

    }

    function exportHeadTxt ($head) {

        file_put_contents($this->saveDir . "head.txt", $head);

    }

    function archiveZip () {

        $zip = new ZipArchive();
        $ret = $zip->open($this->category . "_" . $this->address ."_". $_SERVER['REQUEST_TIME'].".zip",
            ZipArchive::CREATE | ZipArchive::OVERWRITE
        );

        if ($ret !== TRUE) {
            printf('Failed with code %d', $ret);

        } else {

            $options = array('add_path' => 'data/', 'remove_all_path' => TRUE);
            $zip->addGlob("./" . $this->category . "_" . $this->address."/*.{dat,txt,json}", GLOB_BRACE, $options);
            $zip->close();

            print "Saved: ". $this->category . "_" . $this->address ."_". $_SERVER['REQUEST_TIME'].".zip\n";

        }

        return;

    }

}

$shitaraba = new Shitaraba();
$shitaraba->main();

?>
