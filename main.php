<?php
error_reporting(0);

define("_HOST", "jbbs.shitaraba.net");
define("_INTERVAL", 500000);

class Shitaraba {

    public $category, $address;
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
                    "datURL" => "https://"._HOST."/bbs/rawmode.cgi/".$this->setting->DIR."/".$this->setting->BBS."/".$matchs[1]."/"
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
            $this->getDat($data);
            usleep(_INTERVAL);
        }

        return;

    }

    function getDat ($data) {

        // timeout 10sec
        $options = stream_context_create(array( 
            'http' => array( 
                'timeout' => 15
                )
            )
        );

        print $data["datURL"] . " ... ";

        $dat = file_get_contents($data["datURL"], 0, $options);

        if (!$dat) {
            die("ERR timeout\n");
        }

        if (!is_dir("./" . $this->category . "_" . $this->address . "/"))
            mkdir("./" . $this->category . "_" . $this->address . "/");

        file_put_contents("./" . $this->category . "_" . $this->address . "/" . $data["threadkey"].".dat", $dat);

        print strlen($dat) . " bytes OK\n";

        return;

    }

    function exportSetting () {

        file_put_contents("./" . $this->category . "_" . $this->address . "./setting.json", json_encode($this->setting, JSON_UNESCAPED_UNICODE));

        return;

    }

    function exportHeadTxt ($head) {

        file_put_contents("./" . $this->category . "_" . $this->address . "./head.txt", $head);

    }

    function archiveZip () {

        $zip = new ZipArchive();
        $ret = $zip->open($this->category . "_" . $this->address ."_". $_SERVER['REQUEST_TIME'].".zip", ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($ret !== TRUE) {
            printf('Failed with code %d', $ret);

        } else {

            $options = array('add_path' => 'data/', 'remove_all_path' => TRUE);
            $zip->addGlob("./" . $this->category . "_" . $this->address."/*.{dat,txt,json}", GLOB_BRACE, $options);
            $zip->close();

            print "Saved: ". $this->category . "_" . $this->address ."_". $_SERVER['REQUEST_TIME'].".zip";

        }

        return;

    }

}

$shitaraba = new Shitaraba();
$shitaraba->main();


?>