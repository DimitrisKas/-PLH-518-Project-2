<?php

namespace Models\Mongo;

use RestAPI\iRestObject;
use Models\Generic\User;
use MongoDB\BSON\ObjectId;

/**
 * Class User
 * @package Models
 * @implements User
 */
class UserM extends User implements iRestObject {

    public function __construct($obj) {
        parent::__construct($obj);
    }

    /**
     * @param $obj User
     * @return bool TRUE on success, FALSE otherwise
     */
    public static function addOne($obj): bool
    {
        if (empty($obj->username))
        {
            logger("[USER_DB] Username was empty.");
            return false;
        }
        if (empty($obj->password))
        {
            logger("[USER_DB] Password was empty.");
            return false;
        }

        $db = connect();
        $coll = $db->selectCollection("Users");
        $coll->insertOne($obj);
        return true;
    }

    public static function searchByUsername(string $username): User
    {
        $db = connect();
        $coll = $db->selectCollection("Users");
        $cursor = $coll->find(['username' => $username]);


    }

    public static function getOne(string $id): User
    {
        $db = connect();
        $coll = $db->selectCollection("Users");
        $user_doc = $coll->findOne([
            '_id' => new ObjectId('594d5ef280a846852a4b3f70')
        ]);

        return new User($user_doc);
    }

    public static function updateOne(string $id): bool
    {

    }


    public static function deleteOne(string $id): bool
    {

    }

    public static function getAll(): array
    {

    }
}