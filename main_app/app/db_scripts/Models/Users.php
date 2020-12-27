
<?php

class User
{
    public string $id;
    public string $name;
    public string $surname;
    public string $username;
    public string $password;
    public string $email;
    public string $role;
    public bool $confirmed;

    const ADMIN = "ADMIN";
    const CINEMAOWNER = "CINEMAOWNER";
    const USER = "USER";

    public function __construct($name, $surname, $username, $password, $email, $role, $confirmed)
    {
        $this->name = $name;
        $this->surname = $surname;
        $this->username = $username;
        $this->password = $password;
        $this->email = $email;
        $this->role = $role;
        $this->confirmed = $confirmed;
    }

    /** Wraper function for creating User objects through Document-like arrays.
     *  For Users without ID.
     * @see CreateExistingUserObj
     * @see fromDocumentWithID
     * @param $doc 'Document object that contains all User data
     * @return User Object with user Data
     */
    public static function fromDocument($doc): User
    {
        return new User(
                $doc['name'], $doc['surname'], $doc['username'], $doc['password'],
                $doc['email'], $doc['role'], $doc['confirmed']
        );
    }

    /** Wraper function for creating User objects through Document-like arrays.
     *  For Users with ID.
     * @see CreateExistingUserObj
     * @see fromDocument
     * @param $doc 'Document object that contains all User data
     * @return User Object with user Data
     */
    public static function fromDocumentWithID($doc): User
    {
        return self::CreateExistingUserObj(
            $doc['id'], $doc['name'], $doc['surname'], $doc['username'], $doc['password'],
            $doc['email'], $doc['role'], $doc['confirmed']
        );
    }

    /** Create a User object for User that already exists in Database. (i.e. has an ID)
     * @param $id
     * @param $name
     * @param $surname
     * @param $username
     * @param $password
     * @param $email
     * @param $role
     * @param $confirmed
     * @return User Object of user with given data
     */
    public static function CreateExistingUserObj($id, $name, $surname, $username, $password, $email, $role, $confirmed):User
    {
        $user = new User($name, $surname, $username, $password, $email, $role, $confirmed);
        $user->id = $id;
        return $user;
    }

    /** Add self to database
     * @return bool Success boolean
     */
    public function addToDB():bool
    {
        if (empty($this->username))
        {
            logger("[USER_DB] Username was empty.");
            return false;
        }
        if (empty($this->password))
        {
            logger("[USER_DB] Password was empty.");
            return false;
        }

        $ch = curl_init();
        $url = "http://db-service/users";
        $fields = [
            'username'  => $this->username,
            'name'   => $this->name,
            'surname'   => $this->surname,
            'password'   => $this->password,
            'email'   => $this->email,
            'role'   => $this->role,
            'confirmed'   => FALSE,
        ];


        $fields_string = http_build_query($fields);
        logger("Fields String: " . $fields_string);

        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_POST, true);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);


        // Execute post
        logger("Sending Request...");
        $result = curl_exec($ch);


        // Retrieve HTTP status code
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        logger("HTTP code: ". $http_code);

        if ($http_code == 203 || $http_code == 200)
        {
            logger("User succesfully created!");
            curl_close($ch);
            return true;
        }
        else if ($http_code >= 400)
        {
            logger("User was not created.");
        }
        else if (curl_errno($ch) == 6)
        {
            logger("Could not connect to db-service.");
        }
        else if (curl_errno($ch) != 0 )
        {
            logger("An error occured with cURL.");
            logger("Error: ". curl_error($ch) . " .. errcode: " . curl_errno($ch));
        }

        curl_close($ch);

        return false;
    }


    /** Gets all Users from the DB-Service
     * @return array(bool $success, User $user, string $errorMsg)
     */
    public static function GetAllUsers():array
    {
        logger("Getting all users");
        $ch = curl_init();
        $url = "http://db-service/users";


        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);

        // Execute post
        logger("Sending Request...");
        $result = curl_exec($ch);

        // Retrieve HTTP status code
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        logger("HTTP code: ". $http_code);

        // In case of error
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        curl_close($ch);


        // Parse results
        if ($http_code == 200)
        {
            logger("Retrieved all users");
            $result = json_decode($result, true);

            $users = array();
            $i =0;
            foreach ($result as $user_doc)
            {
                $users[$i++] =  User::fromDocumentWithID($user_doc);
            }
            return array(true, $users, "");
        }
        else if ($http_code >= 400)
        {
            logger("Users could not be retrieved");
            return array(false, array(), $result);
        }
        else if ($errno == 6)
        {
            logger("Could not connect to db-service.");
            return array(false, array(), "Could not connect to Database Service");
        }
        else if ($errno != 0 )
        {
            logger("An error occured with cURL.");
            logger("Error: ". $err . " .. errcode: " . $errno);
            return array(false, array(), "Internal Error: " . $err);
        }

        return array(false, array(), "Undefined Error");
    }

    /** Gets all Users from the DB-Service
     * @param $data object Document object that should contain properly defined keys with edit data
     * @return bool Success boolean
     */
    public static function EditUser($data): bool
    {
        logger("Trying to edit user with id: " . $data['user_id']);

        // Check if Role is set
        if ($data['user_role'] !== USER::ADMIN && $data['user_role'] !== USER::CINEMAOWNER && $data['user_role'] !== USER::USER)
            return false;

        // Validate IsConfirmed:
        if ( !empty($data['user_confirmed']) && $data['user_confirmed'] === "true")
            $isConfirmed = true;
        else
            $isConfirmed = false;


        $username = $data['user_username'];
        $password= $data['user_password'];
        $name = $data['user_name'];
        $surname = $data['user_surname'];
        $email = $data['user_email'];
        $role = $data['user_role'];
        $id = $data['user_id'];

        $ch = curl_init();
        $url = "http://db-service/users/" . $id;
        $fields = [
            'username'  => $username,
            'password'   => $password,
            'name'   => $name,
            'surname'   => $surname,
            'email'   => $email,
            'role'   => $role,
            'confirmed'   => $isConfirmed,
        ];

        $fields_string = http_build_query($fields);
        logger("Fields String: " . $fields_string);

        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);

        // Execute post
        logger("Sending Request...");
        $result = curl_exec($ch);

        // Retrieve HTTP status code
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        logger("HTTP code: ". $http_code);

        // In case of error
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        curl_close($ch);

        // Parse results
        if ($http_code == 204)
            return true;

        else if ($http_code >= 400)
            logger("User could not be edited.");

        else if ($errno == 6)
            logger("Could not connect to db-service.");

        else if ($errno != 0 )
            logger("An error occured with cURL. \n
                Error: ". $err . " .. errcode: " . $errno);

        return false;

    }

    /** Deletes User from Database
     * @param $id User's id to be deleted
     * @return bool Success boolean
     */
    public static function DeleteUser(string $id):bool
    {
        logger("Trying to delete user with id: " . $id);

        $ch = curl_init();
        $url = "http://db-service/users/" . $id;

        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);

        // Execute post
        logger("Sending Request... at ". $url);
        $result = curl_exec($ch);

        // Retrieve HTTP status code
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        logger("HTTP code: ". $http_code);

        // In case of error
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        curl_close($ch);

        // Parse results
        if ($http_code == 204)
            return true;

        else if ($http_code >= 400)
            logger("User could not be deleted.");

        else if ($errno == 6)
            logger("Could not connect to db-service.");

        else if ($errno != 0 )
            logger("An error occured with cURL. \n
                Error: ". $err . " .. errcode: " . $errno);

        return false;
    }


    /** Tries to login a user based on given Username and Password.
     * On Success, returns User model.
     * On Failure, returns NULL user model, along with reason of failure.
     * @param $username string
     * @param $password string
     * @return array(bool $success, User $user, string $errorMsg)
     */
    public static function LoginUser(string $username, string $password): array
    {

        $ch = curl_init();
        $url = "http://db-service/login";
        $fields = [
            'username'  => $username,
            'password'   => $password,
        ];

        $fields_string = http_build_query($fields);
        logger("Fields String: " . $fields_string);

        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_POST, true);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);

        // Execute post
        logger("Sending Request...");
        $result = curl_exec($ch);

        // Retrieve HTTP status code
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        logger("HTTP code: ". $http_code);

        // In case of error
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        curl_close($ch);


        // Parse results
        if ($http_code == 200)
        {
            logger("User succesfully logged in!");
            $user = User::fromDocumentWithID(json_decode($result, true));
            return array(true, $user, "");
        }
        else if ($http_code >= 400)
        {
            logger("User was not created.");
            return array(false, array(), $result);
        }
        else if ($errno == 6)
        {
            logger("Could not connect to db-service.");
            return array(false, array(), "Internal error");
        }
        else if ($errno != 0 )
        {
            logger("An error occured with cURL.");
            logger("Error: ". $err . " .. errcode: " . $errno);
            return array(false, array(), "Internal error");
        }

        return array(false, array(), "Undefined error");
    }

}
