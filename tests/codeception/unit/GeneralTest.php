<?php

namespace inblank\fakestura\tests;

use Codeception\Specify;
use inblank\fakestura\Fakestura;
use yii;
use yii\codeception\TestCase;

class GeneralTest extends TestCase
{
    use Specify;

    /**
     * General test
     */
    public function testUsers()
    {
        $this->specify("we create a list of users of male gender", function () {
            $fake = new Fakestura();
            $count = 20;
            $genderCheck = 0;
            foreach ($fake->users([
                'gender' => 'male',
                'limit' => $count,
            ]) as $user) {
                if ($user['gender'] == 'male') {
                    $genderCheck++;
                }
            }
            expect("all users gender must be 'male'", $genderCheck == $count)->true();
        });

        $this->specify("we create a list of users of male gender", function () {
            Fakestura::clearCache();
            $fake = new Fakestura();
            $count = 20;
            $genderCheck = 0;
            foreach ($fake->users([
                'gender' => 'female',
                'limit' => $count,
            ]) as $user) {
                if ($user['gender'] == 'female') {
                    $genderCheck++;
                }
            }
            expect("all users gender must be 'female'", $genderCheck == $count)->true();
        });

        $this->specify("we create a list of users of both gender", function () {
            Fakestura::clearCache();
            $fake = new Fakestura();
            $count = 20;
            $male = 0;
            $female = 0;
            foreach ($fake->users([
                'limit' => $count,
            ]) as $user) {
                if ($user['gender'] == 'female') {
                    $female++;
                }
                if ($user['gender'] == 'male') {
                    $male++;
                }
            }
            expect("the users list must contain all gender", $male != 0 && $female != 0 && $male + $female == $count)->true();

        });
    }

    public function testLogin()
    {
        $this->specify("we create only unique logins", function () {
            Fakestura::clearCache();
            $fake = new Fakestura();
            $count = 20;
            $loginsList = [];
            for ($i = 0; $i < $count; $i++) {
                $login = $fake->login([
                    'unique' => true,
                ]);
                $loginsList[] = $login;
            }
            verify("logins must be unique", count($loginsList))->equals(count(array_unique($loginsList)));
        });

        $this->specify("we create only unique logins use person data", function () {
            Fakestura::clearCache();
            $fake = new Fakestura();
            $person = $fake->person();
            $count = 20;
            $loginsList = [];
            for ($i = 0; $i < $count; $i++) {
                $login = $fake->login([
                    'unique' => true,
                    'person' => $person['name'],
                ]);
                $loginsList[] = $login;
            }
            verify("logins must be unique", count($loginsList))->equals(count(array_unique($loginsList)));
        });

    }

    public function testEmail()
    {
        $this->specify("we create only unique emails", function () {
            Fakestura::clearCache();
            $fake = new Fakestura();
            $count = 20;
            $emailsList = [];
            for ($i = 0; $i < $count; $i++) {
                $login = $fake->email([
                    'unique' => true,
                ]);
                $emailsList[] = $login;
            }
            verify("logins must be unique", count($emailsList))->equals(count(array_unique($emailsList)));
        });

        $this->specify("we create only unique email use person data and login", function () {
            Fakestura::clearCache();
            $fake = new Fakestura();
            $person = $fake->person();
            $login = $fake->login();
            $count = 20;
            $loginsList = [];
            for ($i = 0; $i < $count; $i++) {
                $login = $fake->login([
                    'unique' => true,
                    'person' => $person['name'],
                    'login' => $login,
                ]);
                $loginsList[] = $login;
            }
            verify("logins must be unique", count($loginsList))->equals(count(array_unique($loginsList)));
        });

    }

    public function testCache(){
        $this->specify("we check clear login cache", function(){
            Fakestura::clearCache('login');
            expect("login cache size must be 0", Fakestura::getLoginCache())->count(0);
            $fake = new Fakestura();
            $fake->login([
                'unique'=>true,
            ]);
            Fakestura::clearCache();
            expect("login cache size must be 0", Fakestura::getLoginCache())->count(0);
        });

        $this->specify("we check clear email cache", function(){
            Fakestura::clearCache('email');
            expect("email cache size must be 0", Fakestura::getEmailCache())->count(0);
            $fake = new Fakestura();
            $fake->email([
                'unique'=>true,
            ]);
            Fakestura::clearCache();
            expect("email cache size must be 0", Fakestura::getEmailCache())->count(0);
        });

        $this->specify("we check caches for unique logins", function(){
            Fakestura::clearCache('login');
            $fake = new Fakestura();
            $login = $fake->login([
                'unique'=>true,
            ]);
            expect("login cache must contain only one login", Fakestura::getLoginCache())->count(1);
            expect("login cache must contain generated login", Fakestura::getLoginCache())->contains($login);
        });

        $this->specify("we check caches for unique emails", function(){
            Fakestura::clearCache('email');
            $fake = new Fakestura();
            $email = $fake->email([
                'unique'=>true,
            ]);
            expect("email cache must contain only one email", Fakestura::getEmailCache())->count(1);
            expect("email cache must contain generated email", Fakestura::getEmailCache())->contains($email);
        });

    }

    public function testAddress()
    {
        $this->specify("we have generate address", function () {
            $fake = new Fakestura();
            $address = $fake->address();

            expect("we can generate address", $address)->internalType('array');
            expect("we must see `country` field", $address)->hasKey('country');
            expect("we must see `postcode` field", $address)->hasKey('postcode');
            expect("we must see `city` field", $address)->hasKey('city');
            expect("we must see `street` field", $address)->hasKey('street');
            expect("we must see `region` field", $address)->hasKey('region');
            expect("we must see `number` field", $address)->hasKey('number');
        });

        $this->specify("we have generate address string", function () {
            $fake = new Fakestura();
            $address = $fake->addressString();
            expect("we can generate random address string", $address)->notEmpty();
            expect("we can generate another random address string", $fake->addressString())->notEquals($address);

            $address = $fake->address();
            $address2 = $fake->addressString([
                'tpl' => '{country}{city}{street}',
                'data' => $address,
            ]);
            expect("we can generate another random address string", $address2)->equals($address['country'] . ' ' . $address['city'] . ' ' . $address['street']);
        });
    }

    protected function tearDown()
    {
        parent::tearDown();
    }
}
