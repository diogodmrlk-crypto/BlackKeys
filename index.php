<?php
session_start();

$usersFile = "usuarios.json";
$logsFile  = "logs.json";

// garantir arquivos
if (!file_exists($usersFile)) {
    $users = [
        "devs" => [
            "senha" => password_hash("blackxits", PASSWORD_DEFAULT),
            "admin" => true,
            "creditos" => 0
        ],
        "usuario1" => [
            "senha" => password_hash("ferraodev", PASSWORD_DEFAULT),
            "admin" => false,
            "creditos" => 10
        ],
        "usuario2" => [
            "senha" => password_hash("ffhx", PASSWORD_DEFAULT),
            "admin" => false,
            "creditos" => 5
        ]
    ];
    file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
}
if (!file_exists($logsFile)) file_put_contents($logsFile, json_encode([]));

$users = json_decode(file_get_contents($usersFile), true) ?? [];

// logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// login
if (isset($_POST['login'])) {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    if (isset($users[$user]) && password_verify($pass, $users[$user]['senha'])) {
        $_SESSION['user'] = $user;

        // registrar log
        $logs = json_decode(file_get_contents($logsFile), true) ?? [];
        $logs[] = [
            'id' => (count($logs) + 1),
            'usuario' => $user,
            'acao' => 'Login',
            'key' => null,
            'data' => date("Y-m-d H:i:s"),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        file_put_contents($logsFile, json_encode($logs, JSON_PRETTY_PRINT));

        header("Location: painel.php");
        exit;
    } else {
        $erro = "Usuário ou senha incorretos.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>BLACK KEYS - Login</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700&display=swap" rel="stylesheet">
<style>
body{background:#000;color:#fff;font-family:Montserrat;text-align:center;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;}
.box{width:370px;background:#12000a;padding:28px;border-radius:14px;border:2px solid #ff1744;box-shadow:0 0 20px #ff1744;}
input,button{width:90%;padding:12px;margin:10px 0;border:none;border-radius:10px;background:#1a0008;color:#fff;font-size:1.1em;box-shadow:0 0 10px #ff1744aa;}
button{background:#ff1744;font-weight:bold;cursor:pointer;}
button:hover{background:#30000a;color:#ff1744;}
.link{color:#ff1744;margin-top:10px;display:block;}
.error{color:#ff4c4c;margin-top:8px;}
</style>
</head>
<body>

<div class="box">
    <h1 style="color:#ff1744;">BLACK KEYS</h1>
    <form method="post" autocomplete="off">
        <input name="username" placeholder="Usuário" required>
        <input name="password" type="password" placeholder="Senha" required>
        <button name="login">Entrar</button>
    </form>

    <?php if(isset($erro)) echo "<div class='error'>$erro</div>"; ?>

    <a class="link" href="registrar.php">Criar conta</a>
</div>

</body>
</html>