<?php
session_start();

include_once('db_scripts/Models/Users.php');
include_once('db_scripts/keyrock_api.php');
include_once('Utils/Random.php');
include_once('Utils/Logs.php');
include_once('Utils/util_funcs.php');

logger("-- In Administration");

// Check if User is logged in AND is an Admin
if (isset($_SESSION['login'])
    && $_SESSION['login'] === true
    && isset($_SESSION['user_role'])
    && $_SESSION['user_role'] === User::ADMIN)
{
    LogoutIfInactive();

    // User already logged in...
    logger("User: " . $_SESSION['user_username']);
    logger("Role: " . $_SESSION['user_role']);
}
else
{
    // Redirect to index
    $feedback = "true";
    $f_title = "You do not have access to that page.";
    $f_msg_count = 0;
    $f_color = "f-error";
    ?>
    <form id="toIndex" action="./index.php" method="post">
        <input type="hidden" name="feedback" value="<?php echo $feedback?>">
        <input type="hidden" name="f_color" value="<?php echo $f_color?>">
        <input type="hidden" name="f_title" value="<?php echo $f_title?>">
        <input type="hidden" name="f_msg_count" value="<?php echo $f_msg_count?>">
    </form>
    <script type="text/javascript">
        document.getElementById("toIndex").submit();
    </script>
    <?php
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - CineMania</title>
    <link rel='stylesheet' type='text/css' href='CSS/main.css' />
    <link rel='stylesheet' type='text/css' href='CSS/administration.css' />
</head>

<body class="no-overflow">
    <?php // ---- Navigation Panel - START ----?>
    <div class="top-nav">
        <div class="nav-items">
            <h5 id="top-nav-title">CineMania</h5>
            <a href="welcome.php">Home</a>
            <a href="movies.php">Movies</a>
            <?php
            if ($_SESSION['user_role'] === USER::CINEMAOWNER)
                echo '<a href="owner.php">Owner Panel</a> ';

            if ($_SESSION['user_role'] === USER::ADMIN)
                echo '<a href="administration.php">Admin Panel</a>';
            ?>
        </div>
        <form id="logout-form" method="post" action="./index.php?logout" class="fl-row">
            <span id="username-span"><?php echo $_SESSION['user_username'] ?></span>
            <button type="submit" class="btn-primary">Logout</button>
        </form>
    </div>
    <?php // ---- Navigation Panel - END ----?>

    <div class="main-content" id="admin_content">
        <div id="popup-box-cont" class="f-success" hidden>
            <p id="popup-box-text" ></p>
        </div>
        <div class="card">
            <h4>Manage Users</h4>
            <hr/>

            <div id="admin-table-container" class="table-container">
            <?php

                //TODO remove duplicate code with /async/users_get
                list($isSuccessful, $users, $errmsg)= User::GetAllUsers();
                /* @var $user User (IDE type hint) */
                if ($isSuccessful == false)
                {
                    ?>
                    <div class="feedback-box f-error">
                        <h5 class="feedback-title">Error retrieving Users:</h5>
                        <p class="feedback-text"> <?php echo $errmsg?></p>
                    </div>
                    <?php
                }
                else
                {
                    ?>
                    <table id="admin-table">
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Surname</th>
                            <th>Password</th>
                            <th>E-mail</th>
                            <th>Role</th>
                            <th>Confirmed</th>
                            <th></th>
                            <th></th>
                        </tr>

                    <?php
                    foreach ($users as $user)
                    {
                    ?>
                        <tr id="user_<?php echo $user->k_id?>" onclick="toggleHighlight(this)">
                            <td><div><input id="<?php echo $user->k_id?>_id" type="text" value="<?php echo $user->k_id?>" class="disabled-input id-field" disabled/></div></td>
                            <td><div><input id="<?php echo $user->k_id?>_username" type="text" value="<?php echo $user->username?>" class="custom-input"/></div></td>
                            <td><div><input id="<?php echo $user->k_id?>_name" type="text" value="<?php echo $user->name?>" class="custom-input"/></div></td>
                            <td><div><input id="<?php echo $user->k_id?>_surname" type="text" value="<?php echo $user->surname?>" class="custom-input"/></div></td>
                            <td><div><input id="<?php echo $user->k_id?>_password" type="text" value="" placeholder="Enter new password..." class="custom-input"/></div></td>
                            <td><div><input id="<?php echo $user->k_id?>_email" type="text" value="<?php echo $user->email?>" class="custom-input"/></div></td>
                            <td>
                                <div>
                                    <select id="<?php echo $user->k_id?>_role" name="role">
                                        <option value="ADMIN" <?php echo $user->role === User::ADMIN ? "selected" : "" ?>>Admin</option>
                                        <option value="CINEMAOWNER" <?php echo $user->role === User::CINEMAOWNER ? "selected" : "" ?>>Cinema Owner</option>
                                        <option value="USER" <?php echo $user->role === User::USER ? "selected" : "" ?>>User</option>
                                    </select>
                                </div>
                            </td>
                            <td><div><input id="<?php echo $user->k_id?>_confirmed" type="checkbox" <?php echo $user->confirmed ? "checked" : ""?>/></div></td>
                            <td class="action-td">
                                <div><button id="<?php echo $user->k_id?>_submit" class="btn-primary btn-success" onclick="submitUser('<?php echo $user->k_id?>')" >Save</button></div>
                            </td>
                            <td class="action-td">
                                <div><button id="<?php echo $user->k_id?>_delete" class="btn-primary btn-danger" onclick="deleteUser('<?php echo $user->k_id?>')" >Delete</button></div>
                            </td>
                        </tr>
                    <?php
                    } // For loop

                    echo "</table>";

                } // If Users Retrieved
                ?>
            </div>
            <h4>Server Logs</h4>
            <hr/>
            <textarea id="temp-logs" style="width: 100%; height: 800px; font-size: large; background: #222; color: #f0f0f0"><?php echo getLogs() ?></textarea>
            <h4>DB Service Logs</h4>
            <hr/>
            <textarea id="temp-db-service" style="white-space: pre-wrap;width: 100%; height: 800px; font-size: large; background: #222; color: #f0f0f0"></textarea>
            <button id="refresh-db-btn" class="btn-primary" style="padding: 10px" onclick="updateDBServiceLogs()">Refresh</button>
        </div>

    </div>
</body>
<script type="text/javascript">

    // Scroll logs to bottom
    let textarea = document.getElementById('temp-logs');
    textarea.scrollTop = textarea.scrollHeight;
    updateDBServiceLogs();

    document.getElementById("popup-box-cont").hidden = false;

    function getUsers()
    {
         fetch('async/users_get.php', {
            method: 'POST',
        })
            .then( response => {
                response.text()
                    .then( text => {
                        let container = document.getElementById("admin-table-container");
                        container.innerHTML = text;
                    });
            });
    }

    function submitUser(uid)
    {
        this.event.stopPropagation();
        fetch('async/user_edit.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                'user_id': uid,
                'user_username': document.getElementById(uid+'_username').value,
                'user_name': document.getElementById(uid+'_name').value,
                'user_surname': document.getElementById(uid+'_surname').value,
                'user_password': document.getElementById(uid+'_password').value,
                'user_email': document.getElementById(uid+'_email').value,
                'user_role': document.getElementById(uid+'_role').value,
                'user_confirmed': document.getElementById(uid+'_confirmed').checked ? 'true' : 'false'
            })
        })
            .then( response => {
                return response.json();
            })
            .then( success =>{
                showModal(success);
                getUsers();
            });

    }

    function deleteUser(uid)
    {
        this.event.stopPropagation();
        fetch('async/user_delete.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ 'user_id': uid})
        })
            .then( response => {
                return response.json();
            })
            .then( success =>{
                showModal(success);
                getUsers();
            });
    }

    function toggleHighlight(row)
    {
        let rows = document.getElementById("admin-table").children[0].children;
        Array.from(rows).forEach( row => row.classList.remove("highlighted-row"));
        row.classList.add("highlighted-row");
    }

    function updateDBServiceLogs()
    {
        fetch('async/getDBServicelogs.php')
            .then( response => {
                return response.text()
            })
            .then( text => {
                let textarea = document.getElementById('temp-db-service');
                textarea.innerHTML = text;
                textarea.scrollTop = textarea.scrollHeight;
            })
    }

    function showModal(isSuccessful)
    {
        let text_obj = document.getElementById("popup-box-text");
        let cont_obj = document.getElementById("popup-box-cont");
        // document.getElementById("popup-box-cont").classList.remove("popup-hidden");

        document.getElementById("popup-box-cont").classList.add("popup-show");

        if (isSuccessful)
        {
            text_obj.innerText = "Success!";
            cont_obj.classList.remove("f-warning");
            cont_obj.classList.add("f-success");
        }
        else
        {
            text_obj.innerText = "An error occured!";
            cont_obj.classList.remove("f-success");
            cont_obj.classList.add("f-error");
        }

        setTimeout(function () { document.getElementById("popup-box-cont").classList.remove("popup-show");}, 2500);
    }

</script>
</html>












