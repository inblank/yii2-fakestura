<?php
namespace inblank\fakestura;

use yii\base\Component;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;

/**
 * Extension
 *
 * @link https://github.com/inblank/yii2-fakestura
 * @copyright Copyright (c) 2016 Pavel Aleksandrov <inblank@yandex.ru>
 * @license http://opensource.org/licenses/MIT
 */
class Fakestura extends Component
{
    /**
     * Data for generation
     * @var array
     */
    public static $data = [];
    /**
     * Folder where stored the data files
     * @var string
     */
    public static $dataPath;
    /**
     * Global templates
     * @var array
     */
    protected static $globalTemplates;
    /**
     * Generated login cache for check unique
     * @var array
     */
    private static $loginCache = [];
    /**
     * Generated email cache for check unique
     * @var array
     */
    private static $emailCache = [];
    /**
     * Loaded template
     * @var []
     */
    public $templates = [];
    /**
     * Used language
     * @var string
     */
    protected $language;
    /**
     * List of avatars for generate.
     * Take avatars from https://randomuser.me
     * @var array
     */
    protected $avatarList = ['male'=>[], 'female'=>[]];
    /**
     * Get global templates
     * @return array|mixed
     */
    public static function getGlobalTemplates()
    {
        if (self::$globalTemplates === null) {
            self::$globalTemplates = [];
            // load global templates
            $templateFilename = self::getAbsoluteFilename('templates.php');
            if (file_exists($templateFilename)) {
                self::$globalTemplates = include($templateFilename);
            }
        }
        return self::$globalTemplates;
    }

    /**
     * Get absolute filename for loading file
     * @param string $filename relative filename
     * @return string
     */
    protected static function getAbsoluteFilename($filename)
    {
        if (self::$dataPath === null) {
            self::$dataPath = FileHelper::normalizePath(__DIR__ . '/data') . DIRECTORY_SEPARATOR;
        }
        return FileHelper::normalizePath(self::$dataPath . DIRECTORY_SEPARATOR . $filename);
    }

    /**
     * Clear login and email cache
     * @param string $type type of clearing cache. login, email. If not set clear all caches
     */
    public static function clearCache($type = null)
    {
        if ($type === null || $type == 'login') {
            self::$loginCache = [];
        }
        if ($type === null || $type == 'email') {
            self::$emailCache = [];
        }
    }

    /**
     * Get login cache
     */
    public static function getLoginCache()
    {
        return self::$loginCache;
    }

    /**
     * Get email cache
     */
    public static function getEmailCache()
    {
        return self::$emailCache;
    }

    /**
     * Initialization
     */
    public function init()
    {
        if ($this->templates === null) {
            $this->templates = self::$globalTemplates;
        }
        if ($this->language === null) {
            $this->setLanguage('ru');
        }
    }

    /**
     * Get current language
     * @return string
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * Set used language
     * @param string $language language to set (ru, en, ... ). Value will be converted to lowercase.
     */
    public function setLanguage($language)
    {
        $language = strtolower($language);
        if ($this->language == $language) {
            // language already set
            return;
        }
        $this->language = $language;
        $this->templates = self::$globalTemplates;
        if (!empty($this->language)) {
            $templateFilename = self::getAbsoluteFilename($this->language . '/templates.php');
            if (file_exists($templateFilename)) {
                $this->templates = array_merge(self::getGlobalTemplates(), include($templateFilename));
            }
        }
    }

    /**
     * Get fake user list
     * @param array $config generator config
     *  'tplName' - name of template from template config for use to generate. Default: 'person'
     *  'tpl' - template for use to generate. Priority over 'tplName'
     *  'gender' - gender of person - 'male' or 'female'. If not set, random
     *  'seed' - seed of random generator. If null, not use
     *  'limit' - limit of person. Must be greater than 0. Default: 1
     * @return array [id, name, gender, birth, login, email, password, image]
     */
    public function users($config = [])
    {
        $config = array_merge([
            'tplName' => 'person',
            'tpl' => null,
            'gender' => null,
            'birth' => null,
            'seed' => null,
            'limit' => 1,
        ], $config);
        if ($config['tplName'] === null) {
            $config['tplName'] = 'person';
        }
        if ($config['seed'] !== null) {
            // seed for pseudo random
            srand($config['seed']);
        }
        if ($config['limit'] < 1) {
            // limit not valid
            return [];
        }
        $result = [];
        for ($i = 1; $i <= $config['limit']; $i++) {
            $person = $this->person([
                'gender' => $config['gender'],
                'tpl' => $config['tpl'],
                'tplName' => $config['tplName'],
                'birth' => $config['birth'],
            ]);
            if (empty($person)) {
                // not store empty person
                continue;
            }
            $login = $this->login([
                'person' => $person['name'],
            ]);
            $email = $this->email([
                'person' => $person['name'],
                'login' => $login,
            ]);
            $result[] = [
                'id' => $i,
                'name' => $person['name'],
                'avatar'=> $person['avatar'],
                'gender' => $person['gender'],
                'birth' => $person['birth'],
                'login' => $login,
                'email' => $email,
                'address' => $person['address'],
            ];
        }
        return $result;
    }

    /**
     * Generate person
     * @param array $config generator config
     *  'tplName' - name of template from template config for use to generate. Default: 'person'
     *  'tpl' - template for use to generate. Priority over 'tplName'. Default: null
     *  'gender' - gender of person - 'male' or 'female'. If not set, random. Default: null
     *  'birth' - birth date format in combination of  'd', 'm', 'y'.
     *      'timestamp' - unix_timestamp
     *      'mysql' - mysql DATA field format
     *  Default: mysql
     * @return array person data ['name'=>'', 'gender'=>'', 'birth'=>'']
     */
    public function person($config = [])
    {
        $config = array_merge([
            'tplName' => 'person',
            'tpl' => null,
            'gender' => null,
            'birth' => null
        ], $config);
        if (empty($config['tpl'])) {
            if (!array_key_exists($config['tplName'], self::$globalTemplates)) {
                // not fond template
                return null;
            }
            $config['tpl'] = self::$globalTemplates[$config['tplName']];
        }
        $genderList = ['male', 'female'];
        if (!array_key_exists('person', self::$data)) {
            // load person data for generation
            self::$data['person'] = [];
            foreach ($genderList as $gender) {
                self::$data['person'][$gender] = [];
                foreach (['name', 'middle', 'last'] as $namePart) {
                    self::$data['person'][$gender][$namePart]
                        = self::loadFromFile($this->language . '/' . $gender . '/' . $namePart);
                }
            }
        }
        if ($config['gender'] === null || !in_array($config['gender'], $genderList)) {
            $config['gender'] = $genderList[rand(0, 1)];
        }
        return [
            'avatar' => $this->avatar($config['gender']),
            'name' => self::parseTemplate($config['tpl'], self::$data['person'][$config['gender']]),
            'gender' => $config['gender'],
            'address'=>$this->address(),
            'birth' => $this->birth([
                'format' => $config['birth'],
            ]),
        ];
    }

    /**
     * Load data form file
     * @param string $filename filename for load data
     * @return array
     */
    protected static function loadFromFile($filename)
    {
        $filename = self::getAbsoluteFilename($filename);
        if (!file_exists($filename)) {
            return [];
        }
        return array_map('trim', file($filename));
    }

    /**
     * Template parsing
     * @param string $template template name
     * @param array $data data for the template fields
     * @return string
     */
    protected static function parseTemplate($template, $data)
    {
        // TODO parse optional fields in template {?name}
        preg_match_all('/\{([\w\d-_]+)\}/ismU', $template, $fields);
        foreach ($fields[1] as $i => $field) {
            $value = '';
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
            }
            if (is_array($value)) {
                $value = array_values($value)[rand(0, count($value) - 1)];
            }
            $template = str_replace($fields[0][$i], ' ' . $value, $template);
        }
        return trim($template);
    }

    /**
     * Generate random date of birth
     * @param array $config generator config
     *  'format' - birth date format in combination of  'd', 'm', 'y'.
     *      'timestamp' - unix_timestamp
     *      'mysql' - mysql DATA field format
     *  Default: mysql
     * @return int|mixed
     */
    public function birth($config = [])
    {
        $config = array_merge([
            'min' => 16,
            'max' => 50,
            'format' => null,
        ], $config);
        $config['format'] = strtolower($config['format']);
        if ($config['format'] === null || $config['format'] == 'mysql') {
            $config['format'] = 'y-m-d';
        }
        $now = date('Y');
        $year = rand($now - $config['max'], $now - $config['min']);
        $month = rand(1, 12);
        $day = rand(1, date('t', mktime(0, 0, 1, $month, 1, $year)));
        if ($config['format'] == 'timestamp') {
            return mktime(0, 0, 1, $month, $day, $year);
        }
        return str_replace(
            ['m', 'd', 'y'],
            [
                str_pad($month, 2, '0', STR_PAD_LEFT),
                str_pad($day, 2, '0', STR_PAD_LEFT),
                $year
            ],
            strtolower($config['format'])
        );
    }

    /**
     * Generate login
     * @param array $config generator config
     *  'person' - person name. Randomly used for login generator. Default: null
     *  'unique' - generate only unique login if true. Default: true
     * @return string
     */
    public function login($config = [])
    {
        $config = array_merge([
            'person' => null,
            'unique' => true,
        ], $config);
        if (!array_key_exists('login', self::$data)) {
            self::$data['login'] = array_unique(array_merge(
                self::loadFromFile('login'),
                self::loadFromFile($this->language . '/login')
            ));
        }
        $login = [
            str_replace(' ', '_', self::$data['login'][rand(0, count(self::$data['login']) - 1)])
        ];
        if (!empty($config['person'])) {
            // use person for generate
            $login = array_merge($login, array_map('trim', explode(' ', Inflector::transliterate($config['person']))));
            if (rand(0, 6) > 4) {
                shuffle($login);
            }
            $login = array_slice($login, 0, rand(1, min(2, count($login) - 1)));
        }
        $login = strtolower(implode('.', $login));
        if ($config['unique']) {
            while (!empty(self::$loginCache[$login])) {
                $login .= rand(0, 9);
            }
        }
        self::$loginCache[$login] = true;
        return $login;
    }

    /**
     * Generate email
     * @param array $config generator config
     *  'person' - person name. Randomly used for email generator. Default: null
     *  'login' - login. Randomly used for email generator. Default: null
     *  'unique' - generate only unique email if true. Default: true
     *  'domains' - generate email from domains list. Default: ['example.com', 'example.net', 'example.org']
     * @return string
     */
    public function email($config = [])
    {
        $config = array_merge([
            'person' => null,
            'login' => null,
            'unique' => true,
            'domains' => ['example.com', 'example.net', 'example.org'],
        ], $config);
        $config['domains'] = array_map('strtolower', (array)$config['domains']);
        $email = [];
        if ($config['person'] !== null) {
            $email += array_map('trim', explode(' ', Inflector::transliterate($config['person'])));
        }
        if ($config['login'] === null) {
            $config['login'] = $this->login();
        }
        $email[] = $config['login'];
        shuffle($email);
        $email = strtolower(implode('.', array_slice($email, 0, rand(1, min(2, count($email) - 1)))));
        $domain = $config['domains'][rand(0, count($config['domains']) - 1)];
        if ($config['unique']) {
            while (!empty(self::$emailCache[$email . '@' . $domain])) {
                $email .= rand(0, 9);
            }
        }
        $email .= '@' . $domain;
        self::$emailCache[$email] = true;
        return $email;
    }

    /**
     * Generate address array
     * @return array
     */
    public function address()
    {
        $cities = self::loadFromFile($this->language . '/address/cities');
        $country = array_shift($cities);
        $streets = self::loadFromFile($this->language . '/address/streets');
        $data = explode('/', $cities[rand(0, count($cities) - 1)]);
        return [
            'country' => $country,
            'postcode'=>$data[0],
            'region'=>count($data)==3 ? $data[1] : '',
            'city' =>count($data)==3 ? $data[2] : $data[1],
            'street'=>$streets[rand(0, count($streets)-1)],
            'number'=>rand(9,199),
        ];
    }

    /**
     * Generate address
     * @param array $config
     *  'tplName' - name of template from template config for use to generate. Default: 'address'
     *  'tpl' - template for use to generate. Priority over 'tplName'. Default: null
     *  'data' - data for generate address string. If not set will be use Fakestura::address()
     * @return string
     */
    public function addressString($config = [])
    {
        // build data array
        $config = array_merge([
            'tplName' => 'address',
            'tpl' => null,
            'data' => null,
        ], (array)$config);

        if (empty($config['data'])) {
            $config['data'] = $this->address();
        }
        if (empty($config['tpl'])) {
            if (!array_key_exists($config['tplName'], self::$globalTemplates)) {
                // not fond template
                return null;
            }
            $config['tpl'] = self::$globalTemplates[$config['tplName']];
        }
        return self::parseTemplate($config['tpl'], $config['data']);
    }

    /**
     * Generate random avatar
     * Take avatars from https://randomuser.me
     * @param string $gender avatar gender. male or female
     * @return string
     */
    public function avatar($gender=null){
        if($gender===null){
            $gender = ['men', 'women'][rand(0,1)];
        }elseif(in_array($gender, ['men', 'male'])){
            $gender = 'men';
        }else{
            $gender = 'women';
        }
        if(empty($this->avatarList[$gender])){
            $this->avatarList[$gender] = range(0, 99);
            shuffle($this->avatarList[$gender]);
        }
        return 'https://randomuser.me/api/portraits/'.$gender.'/'.array_pop($this->avatarList[$gender]).'.jpg';
    }
}
