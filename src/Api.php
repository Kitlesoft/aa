<?php

namespace Kitlesoft\Aa;

use Carbon\Carbon;
use GuzzleHttp\Client;

class Api
{
    public const API_URL = 'https://api.aa.com.tr';
    protected $username;
    protected $password;
    protected $attributes = [
        'filter_type' => 1,
        'filter_language' => 1,
        'filter_category' => null,
        'limit' => 100,
    ];
    protected $mediaFormat = 'web';
    protected $summaryLength = 120;
    protected $summaryDot = false;
    protected $auth = ['', ''];

    public function __construct(array $config)
    {
        $this->setParameters($config);
    }

    protected function setParameters(array $config)
    {
        if (!is_array($config)) {
            throw new \Exception('$config variable must be an array.');
        }
        if (array_key_exists('username', $config)) {
            $this->username = $config['username'];
        }
        if (array_key_exists('password', $config)) {
            $this->password = $config['password'];
        }
        if (array_key_exists('mediaFormat', $config)) {
            $this->mediaFormat = $config['mediaFormat'];
        }
        if (array_key_exists('summaryLength', $config)) {
            $this->summaryLength = $config['summaryLength'];
        }
        if (array_key_exists('summaryDot', $config)) {
            $this->summaryDot = $config['summaryDot'];
        }
        $this->auth = [$this->username, $this->password];
    }

    protected function setAttributes(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
    }

    protected function fetchUrl(string $url, string $method = 'GET', array $options = [])
    {
        try {
            $client = new Client();
            $response = $client->request($method, $url, $options);

            return $response->getBody();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function search(array $attributes = [])
    {
        $this->setAttributes($attributes);
        $response = $this->fetchUrl(self::API_URL . '/abone/search', 'POST', [
            'auth' => $this->auth,
            'form_params' => $this->attributes,
        ]);
        $jsonResponse = json_decode($response, true);
        if (isset($jsonResponse["response"]["success"])) {
            if (($jsonResponse["response"]["success"] == true) && isset($jsonResponse["data"]["result"])) {
                return json_encode($jsonResponse["data"]["result"]);
            }
        }

        return false;
    }

    public function document(string $id, string $format)
    {
        $url = self::API_URL . '/abone/document/' . $id . '/' . $format;
        $data = $this->fetchUrl($url, 'GET', ['auth' => $this->auth]);
        if ($data) {
            return $data;
        }

        return false;
    }

    protected function documentLink(string $id, string $format)
    {
        return self::API_URL . '/abone/document/' . $id . '/' . $format;
    }

    protected function toObject($xml)
    {
        if ($xml) {
            $obj = new \stdClass();
            $data = simplexml_load_string($xml);
            $data->registerXPathNamespace("n", "http://iptc.org/std/nar/2006-10-01/");
            $obj->code = (string)$data->itemSet->newsItem['guid'];
            $obj->category = (string)$data->xpath('//n:subject/n:name[@xml:lang="tr"]')[0];
            $obj->title = (string)$data->itemSet->newsItem->contentMeta->headline;
            if (empty($this->clearText($data->itemSet->newsItem->contentSet->inlineXML->nitf->body->{'body.content'}))) {
                $obj->body = null;
            } else {
                $obj->body = $this->clearText($data->itemSet->newsItem->contentSet->inlineXML->nitf->body->{'body.content'});
            }
            $obj->summary = $this->clearText($data->itemSet->newsItem->contentSet->inlineXML->nitf->body->{'body.head'}->abstract) ?: $this->createSummary($obj->body, $this->summaryDot);
            $medias = $data->xpath('//n:newsItem/n:itemMeta/n:link');
            $obj->images = null;
            $obj->videos = null;
            $obj->texts = null;
            $obj->cover = null;
            $obj->city = null;
            $obj->keywords = null;
            foreach ($medias as $row) {
                $qcode = (string)$row->itemClass['qcode'];
                if ($qcode == 'ninat:picture') {
                    $image = (string)$row['residref'];
                    $obj->images[] = ["id" => $image, "link" => $this->documentLink($image, $this->mediaFormat)];
                }
                if ($qcode == 'ninat:video') {
                    $video = (string)$row['residref'];
                    $obj->videos[] = ["id" => $video, "link" => $this->documentLink($video, $this->mediaFormat)];
                }
                if ($qcode == 'ninat:text') {
                    $text = (string)$row['residref'];
                    $obj->texts[] = ["id" => $text, "link" => $this->documentLink($text, 'newsml29')];
                }
            }
            if ($obj->images != null) {
                $obj->cover = $obj->images[0]['id'];
            }
            if (isset($data->xpath('//n:contentMeta/n:located[@type="cptype:city"]/n:name[@xml:lang="tr"]')[0])) {
                $obj->city = (string)$data->xpath('//n:contentMeta/n:located[@type="cptype:city"]/n:name[@xml:lang="tr"]')[0];
            }
            if ($data->xpath('//n:newsItem/n:contentMeta/n:keyword')) {
                $tags = null;
                foreach ($data->xpath('//n:newsItem/n:contentMeta/n:keyword') as $tag) {
                    $tags[] = (string)$tag;
                }
                $obj->keywords = $tags;
            }
            $obj->created_at = (new Carbon($data->itemSet->newsItem->itemMeta->versionCreated))->format('Y-m-d H:i:s');

            return $obj;
        }

        return false;
    }

    protected function createSummary($text, $dot = false)
    {
        if (empty($text)) {
            return null;
        } else {
            if ($dot) {
                return $this->shortenString(strip_tags($text));
            } else {
                $split = explode('.', strip_tags($text));

                return $split[0];
            }
        }
    }

    protected function shortenString($str)
    {
        if (strlen($str) > $this->summaryLength) {
            $str = rtrim(mb_substr($str, 0, $this->summaryLength, 'UTF-8'));
            $str = substr($str, 0, strrpos($str, ' '));
            $str .= '...';
            $str = str_replace(',...', '...', $str);
        }

        return $str;
    }

    public function documentList($response)
    {
        $data = [];
        foreach (json_decode($response) as $item) {
            $document = $this->document($item->id, 'newsml29');
            if ($document) {
                array_push($data, $this->toObject($document));
                usleep(0.5 * 1000000);
            }
        }

        return json_encode($data);
    }

    public function documentSave($id, $saveLocation, $format = 'web')
    {
        $data = $this->document($id, $format);
        file_put_contents($saveLocation, $data);

        return $saveLocation;
    }

    protected function clearText($text)
    {
        $text = html_entity_decode(preg_replace("/\(AA\)?\s?\W/", "", $text));
        $text = trim(str_replace(["\t", "\n", "\r", "\0", "\x0B"], " ", $text), " \x2D");

        return $text;
    }
}
