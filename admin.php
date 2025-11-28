<?php
session_start();

if (!isset($_SESSION["user"])) {
    header("Location: index.php");
    exit;
}

$usersFile = "usuarios.json";
$logsFile  = "logs.json";
$mockapi   = "https://68f27d4fb36f9750deecce00.mockapi.io/keys1";

if (!file_exists($usersFile)) file_put_contents($usersFile, json_encode([]));
if (!file_exists($logsFile)) file_put_contents($logsFile, json_encode([]));

$users = json_decode(file_get_contents($usersFile), true) ?? [];
$logs  = json_decode(file_get_contents($logsFile), true) ?? [];

$u = $_SESSION["user"];
if (!isset($users[$u]) || !($users[$u]['admin'] ?? false)) {
    die("Acesso negado.");
}

$msg = '';

// helper random
function randCode($len = 6){
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $s = '';
    for ($i=0;$i<$len;$i++) $s .= $chars[random_int(0, strlen($chars)-1)];
    return $s;
}

// gerar key (admin, sem custo)
if (isset($_POST['gen_admin_key'])) {
    $prefixo = trim($_POST['prefixo_admin'] ?? '');
    $validade = $_POST['validade_admin'] ?? '';
    if ($prefixo === '' || $validade === '') {
        $msg = "Preencha prefixo e validade.";
    } else {
        $random = randCode(6);
        $key = strtoupper($prefixo) . "-" . strtoupper($validade) . "-" . $random;

        // enviar mockapi
        $payload = [
            "key" => $key,
            "prefixo" => strtoupper($prefixo),
            "validade" => strtoupper($validade),
            "gerada_por" => $u,
            "data" => date("Y-m-d H:i:s")
        ];
        $ch = curl_init($mockapi);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        // registra log
        $logs = json_decode(file_get_contents($logsFile), true) ?? [];
        $logs[] = [
            'id' => (count($logs) + 1),
            'usuario' => $u,
            'acao' => 'Admin gerou key',
            'key' => $key,
            'data' => date("Y-m-d H:i:s"),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'mockapi_status' => $http,
            'mockapi_response' => $resp ?: $curlErr
        ];
        file_put_contents($logsFile, json_encode($logs, JSON_PRETTY_PRINT));

        $msg = ($http>=200 && $http<300) ? "Key gerada e enviada: $key" : "Key gerada: $key (falha ao enviar: $curlErr | status $http)";
    }
}

// adicionar créditos
if (isset($_POST['add'])) {
    $target = $_POST['user'] ?? '';
    $qtd = intval($_POST['qtd'] ?? 0);
    if ($target !== '' && isset($users[$target]) && $qtd > 0) {
        $users[$target]['creditos'] = ($users[$target]['creditos'] ?? 0) + $qtd;
        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
        $msg = "Créditos adicionados para $target.";
    }
}

// remover créditos
if (isset($_POST['rem'])) {
    $target = $_POST['user'] ?? '';
    $qtd = intval($_POST['qtd'] ?? 0);
    if ($target !== '' && isset($users[$target]) && $qtd > 0) {
        $users[$target]['creditos'] = max(0, ($users[$target]['creditos'] ?? 0) - $qtd);
        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
        $msg = "Créditos removidos de $target.";
    }
}

// criar usuário
if (isset($_POST['create_user'])) {
    $newu = trim($_POST['new_user'] ?? '');
    $newp = $_POST['new_pass'] ?? '';
    $isadm = isset($_POST['new_admin']) ? true : false;
    if ($newu === '' || $newp === '') {
        $msg = "Preencha usuário e senha.";
    } elseif (isset($users[$newu])) {
        $msg = "Usuário já existe.";
    } else {
        $users[$newu] = [
            'senha' => password_hash($newp, PASSWORD_DEFAULT),
            'admin' => $isadm,
            'creditos' => 0
        ];
        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
        $msg = "Usuário $newu criado.";
    }
}

// apagar logs
if (isset($_POST['clear_logs'])) {
    file_put_contents($logsFile, json_encode([], JSON_PRETTY_PRINT));
    $msg = "Logs apagados.";
}

// carregar atualizado
$users = json_decode(file_get_contents($usersFile), true) ?? [];
$logs  = json_decode(file_get_contents($logsFile), true) ?? [];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Admin - BLACK KEYS</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700&display=swap" rel="stylesheet">
<style>
body{background:#000;color:#fff;font-family:Montserrat;text-align:center;padding-top:60px;margin:0;}
.container{width:700px;background:#12000a;margin:auto;padding:25px;border:2px solid #ff1744;border-radius:14px;box-shadow:0 0 20px #ff1744;}
.left{float:left;width:48%;padding-right:2%;}
.right{float:right;width:48%;padding-left:2%;}
select,input,button,textarea{width:100%;padding:10px;border-radius:10px;margin:8px 0;border:none;background:#1a0008;color:#fff;box-shadow:0 0 10px #ff1744aa;}
button{background:#ff1744;font-weight:bold;cursor:pointer;padding:10px;}
button:hover{background:#30000a;color:#ff1744;}
.logbox{height:220px;overflow-y:auto;padding:10px;background:#1a0008;border-radius:10px;text-align:left;font-size:0.9em;}
.clear{background:#444;padding:8px;border-radius:8px;color:#fff;margin-top:8px;cursor:pointer;}
.row:after{content:'';display:block;clear:both;}
#hamburger{position:fixed;top:20px;left:20px;font-size:40px;cursor:pointer;display:block;z-index:9999;}
#adminMenu{position:fixed;top:0;left:-260px;width:250px;height:100%;background:#12000a;padding:25px;border-right:2px solid #ff1744;box-shadow:0 0 20px #ff1744;transition:0.3s;z-index:9998;}
#adminMenu h2{color:#ff1744;}#adminMenu a{display:block;margin:15px 0;color:#fff;text-decoration:none;font-size:1.1em;}#adminMenu a:hover{color:#ff1744;}
</style>
</head>
<body>

<div id="hamburger">☰</div>
<div id="adminMenu">
    <h2>Admin</h2>
    <a href="painel.php">Voltar ao painel</a>
    <a href="index.php?logout=1">Sair</a>
</div>

<div class="container">
    <h1 style="color:#ff1744;">Painel Administrativo</h1>
    <?php if($msg) echo "<p style='color:#00ff00;'>".htmlspecialchars($msg)."</p>"; ?>

    <div class="row">
        <div class="left">
            <h3>Usuários</h3>
            <div style="background:#0f0f0f;padding:10px;border-radius:10px;">
                <?php foreach($users as $nome => $d): ?>
                    <div style="padding:6px;border-bottom:1px solid #222;">
                        <b><?=htmlspecialchars($nome)?></b> — Créditos: <?=intval($d['creditos'] ?? 0)?> — Admin: <?=(!empty($d['admin'])?'Sim':'Não')?>
                    </div>
                <?php endforeach; ?>
            </div>

            <h3 style="margin-top:12px;">Adicionar / Remover Créditos</h3>
            <form method="post">
                <select name="user" required>
                    <?php foreach($users as $nome => $d): ?>
                        <option value="<?=htmlspecialchars($nome)?>"><?=htmlspecialchars($nome)?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="qtd" placeholder="Quantidade" min="1" required>
                <button type="submit" name="add">Adicionar Créditos</button>
                <button type="submit" name="rem" style="background:#444;margin-top:8px;">Remover Créditos</button>
            </form>

            <h3 style="margin-top:12px;">Criar Usuário</h3>
            <form method="post">
                <input name="new_user" placeholder="Usuário" required>
                <input name="new_pass" type="password" placeholder="Senha" required>
                <label style="display:block;text-align:left;"><input type="checkbox" name="new_admin"> Tornar admin</label>
                <button type="submit" name="create_user">Criar usuário</button>
            </form>
        </div>

        <div class="right">
            <h3>Gerar Key (Admin)</h3>
            <form method="post">
                <input name="prefixo_admin" placeholder="Prefixo (ex: FERRAO-CHEATS)" required>
                <select name="validade_admin" required>
                    <option value="1D">1 Dia</option>
                    <option value="3D">3 Dias</option>
                    <option value="7D">7 Dias</option>
                    <option value="30D">30 Dias</option>
                    <option value="12H">12 Horas</option>
                    <option value="24H">24 Horas</option>
                </select>
                <button type="submit" name="gen_admin_key">Gerar Key (enviar à API)</button>
            </form>

            <h3 style="margin-top:12px;">Logs</h3>
            <div class="logbox">
                <?php if(empty($logs)) echo "<i>Nenhum log.</i>"; ?>
                <?php foreach(array_reverse($logs) as $l): ?>
                    <div style="padding:6px;border-bottom:1px solid #222;">
                        <b><?=htmlspecialchars($l['usuario'] ?? '')?></b> — <?=htmlspecialchars($l['acao'] ?? '')?><br>
                        <?=htmlspecialchars($l['data'] ?? '')?> — Key: <?=htmlspecialchars($l['key'] ?? '')?><br>
                        <small>IP: <?=htmlspecialchars($l['ip'] ?? '')?> — API status: <?=htmlspecialchars($l['mockapi_status'] ?? '')?></small>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="post" style="margin-top:8px;">
                <button type="submit" name="clear_logs" class="clear">Apagar logs</button>
            </form>
        </div>
    </div>

    <div style="clear:both"></div>
</div>

<script>
let menu = document.getElementById("adminMenu");
let btn = document.getElementById("hamburger");
btn.onclick = () => {
    menu.style.left = (menu.style.left === "0px") ? "-260px" : "0px";
}
</script>

</body>
</html>