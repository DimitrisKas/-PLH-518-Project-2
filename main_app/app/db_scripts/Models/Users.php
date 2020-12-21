
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

    const ID_PREFIX = "u";

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

//            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
//                'Content-Type: application/x-www-form-urlencoded',
//            ));


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


        // Debugging
//        logger("Raw result: ". $result);
//        $res = json_decode($result, true);
//        $data = $res['response']['data'][0];
//        logger("Data:" . var_export($data, true));

        curl_close($ch);

        return false;
    }

    private function generateID()
    {
        do {
            $this->id = getRandomString(9, $this::ID_PREFIX);
        } while($this->checkIfUniqueID() === false);
    }

    public function checkIfUniqueID():bool
    {
        $conn = OpenCon(true);

        $sql_str = "SELECT ID FROM Users WHERE id=?";
        $stmt = $conn->prepare($sql_str);
        $stmt->bind_param("s",$id);
        $id = $this->id;

        if (!$stmt->execute())
            logger("[USER_DB] Check UID failed " . $stmt->error);

        if ($stmt->affected_rows === 1)
            return false;
        else
            return true;
    }

    public function checkIfUniqueUsername():bool
    {
        $conn = OpenCon(true);

        $sql_str = "SELECT ID FROM Users WHERE username=?";
        $stmt = $conn->prepare($sql_str);
        $stmt->bind_param("s",$this->username);

        if (!$stmt->execute())
            logger("[USER_DB] Check Username failed " . $stmt->error);

        if ($stmt->affected_rows === 1)
            return false;
        else
            return true;
    }

    // static functions
    public static function CreateExistingUserObj($id, $name, $surname, $username, $password, $email, $role, $confirmed):User
    {
        $user = new User($name, $surname, $username, $password, $email, $role, $confirmed);
        $user->id = $id;
        return $user;
    }

    public static function DeleteUser(string $id):bool
    {
        logger("[USER_DB] Trying to delete user with id: " . $id);
        $success = false;

        $conn = OpenCon(true);

        $sql_str = "DELETE FROM Users WHERE id=?";
        $stmt = $conn->prepare($sql_str);
        $stmt->bind_param("s",$id);

        if (!$stmt->execute())
        {
            logger("[USER_DB] Remove User failed " . $stmt->error);
            $success = false;
        }
        else
        {
            logger("[USER_DB] Removed user successfully!");
            $success = true;
        }

        // Clean up
        $stmt->close();
        CloseCon($conn);

        return  $success;
    }

    public static function EditUser($data):bool
    {
        logger("[USER_DB] Trying to edit user with id: " . $data['user_id']);

        $conn = OpenCon(true);

        if ($data['user_role'] !== USER::ADMIN && $data['user_role'] !== USER::CINEMAOWNER && $data['user_role'] !== USER::USER)
            return false;

        $sql_str = "UPDATE Users SET USERNAME=? , NAME=?, SURNAME=?, EMAIL=?, ROLE=?, CONFIRMED=? WHERE id=?";
        $stmt = $conn->prepare($sql_str);
        $stmt->bind_param("sssssis",$username, $name, $surname, $email, $role, $confirmed, $id);

        // Validate IsConfirmed:
        if ( !empty($data['user_confirmed']) && $data['user_confirmed'] === "true")
            $isConfirmed = true;
        else
            $isConfirmed = false;


        $username = $data['user_username'];
        $name = $data['user_name'];
        $surname = $data['user_surname'];
        $email = $data['user_email'];
        $role = $data['user_role'];
        $confirmed = $isConfirmed;
        $id = $data['user_id'];

        if (!$stmt->execute())
        {
            logger("[USER_DB] Edit User failed " . $stmt->error);
            $success = false;
        }
        else
        {
            logger("[USER_DB] Edited user successfully!");
            $success = true;
        }
        $stmt->close();

        // Check if user wants to change password
        $success_pass = true;
        if ( !empty($data['user_password']) )
        {
            $sql_str = "UPDATE Users SET PASSWORD=? WHERE id=?";
            $stmt = $conn->prepare($sql_str);
            $stmt->bind_param("ss",$password, $id);

            $password = $data['user_password'];
            $id = $data['user_id'];

            if (!$stmt->execute())
            {
                logger("[USER_DB] Password change failed: " . $stmt->error);
                $success_pass = false;
            }
            else
            {
                logger("[USER_DB] Changed User Password successfully!");
                $success_pass = true;
            }
            $stmt->close();
        }

        // Clean up
        CloseCon($conn);

        // If everything was successful
        return  $success && $success_pass;
    }

    public static function GetAllUsers():array
    {
        $conn = OpenCon(true);

        $sql_str = "SELECT * FROM Users";
        $stmt = $conn->prepare($sql_str);

        if (!$stmt->execute())
            logger("[USER_DB] Get users failed " . $stmt->error);

        $result = $stmt->get_result();

        $num_of_rows = $result->num_rows;
        logger("[USER_DB] Found " . $num_of_rows . " users.");

        $ret_array = array();
        while ($row = $result->fetch_assoc()) {

            // Create object and append to return array
            $user = User::CreateExistingUserObj(
                $row['ID'], $row['NAME'], $row['SURNAME'], $row['USERNAME'],
                $row['PASSWORD'], $row['EMAIL'], $row['ROLE'] ,$row['CONFIRMED']);
            $ret_array[] = $user;

            $msg = 'ID: '.$row['ID'] . ', Username: '. $row['USERNAME'] . ', Role: '. $row['ROLE'];
            logger('[USER_DB] '.$msg);
        }

        $stmt->free_result();
        $stmt->close();

        CloseCon($conn);

        return $ret_array;
    }

    /** Tries to login a user based on given Username and Password.
     * On Success, returns User model.
     * On Failure, returns NULL user model, along with reason of failure.
     * @param $username string
     * @param $password string
     * @return array($successBool, $user, $errorMsg)
     */
    public static function LoginUser(string $username, string $password)
    {
        // TODO: Add confirmated user check
        $conn = OpenCon(true);

        $sql_str = "SELECT * FROM Users WHERE USERNAME=? AND PASSWORD=?";
        $stmt = $conn->prepare($sql_str); 
        $stmt->bind_param("ss",$_username, $_password);
        $_username = $username;
        $_password = $password;

        if (!$stmt->execute())
            logger("[USER_DB] Login User statment bind failed: " . $stmt->error);

        $result = $stmt->get_result();

        $num_of_rows = $result->num_rows;
        logger("[USER_DB] Found " . $num_of_rows . " users.");

        // If USERNAME - PASSWORD pair is found
        if ($num_of_rows === 1)
        {
            $row = $result->fetch_assoc();

            if($row['CONFIRMED'] === 0)
                $return_arr = array(false, null, "You are not yet confirmed.");
            else
            {
                $user = User::CreateExistingUserObj(
                        $row['ID'], $row['NAME'], $row['SURNAME'], $row['USERNAME'],
                        $row['PASSWORD'], $row['EMAIL'], $row['ROLE'] ,$row['CONFIRMED']);
                $return_arr = array(true, $user, "");
            }
        }
        else
        {
            $sql_str = "SELECT * FROM Users WHERE USERNAME=?";
            $stmt = $conn->prepare($sql_str); $stmt = $conn->prepare($sql_str);
            $stmt->bind_param("s",$_username);
            $_username = $username;

            if (!$stmt->execute())
                logger("[USER_DB] Login User statment bind failed: " . $stmt->error);

            $result = $stmt->get_result();

            $num_of_rows = $result->num_rows;
            logger("[USER_DB] Found " . $num_of_rows . " users.");

            if ($num_of_rows === 1)
            {
                logger("[USER_DB] Couldn't authenticate user: ". $username);
                $return_arr = array(false, null, "Wrong password");
            }
            else
            {
                logger("[USER_DB] Couldn't find user: ". $username);
                $return_arr = array(false, null, "Couldn't find User");
            }
        }

        // Cleanup
        $stmt->free_result();
        $stmt->close();
        CloseCon($conn);

        return $return_arr;

    }

}
