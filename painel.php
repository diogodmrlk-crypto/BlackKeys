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
$isAdmin = isset($users[$u]["admin"]) && $users[$u]["admin"] === true;
$creditos = $users[$u]["creditos"] ?? 0;
$msg = '';

// helper gerar random (6 chars)
function randCode($len = 6){
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $s = '';
    for ($i=0;$i<$len;$i++) $s .= $chars[random_int(0, strlen($chars)-1)];
    return $s;
}

// custo: para D -> número de dias; para H -> number of hours
function custoPorValidade($validade){
    // validade format: 1D 3D 12H 24H 30D
    $num = intval($validade);
    return max(1, $num); // custo = número (mínimo 1)
}

// processa gerar key (usuário pode gerar mas gasta créditos)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gerar'])) {
    $prefixo = trim($_POST['prefixo'] ?? '');
    $validade = $_POST['validade'] ?? '';
    $num = intval($validade);
    // validade comes like "1D" or "12H"
    if ($prefixo === '' || $validade === '') {
        $msg = "Preencha prefixo e validade.";
    } else {
        $cost = custoPorValidade($validade);
        // admin não paga
        if (!$isAdmin && ($users[$u]["creditos"] ?? 0) < $cost) {
            $msg = "Créditos insuficientes. Você precisa de $cost créditos.";
        } else {
            // montar key: PREFIXO-VALIDADE-RANDOM
            $random = randCode(6);
            $key = strtoupper($prefixo) . "-" . strtoupper($validade) . "-" . $random;

            // se não é admin, desconta créditos
            if (!$isAdmin) {
                $users[$u]["creditos"] = max(0, ($users[$u]["creditos"] ?? 0) - $cost);
                file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
            }

            // enviar para MockAPI (formato B)
            $payload = [
                "key" => $key,
                "prefixo" => strtoupper($prefixo),
                "validade" => strtoupper($validade),
                "gerada_por" => $u,
                "data" => date("Y-m-d H:i:s")
            ];
            // POST via cURL
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

            // registrar log
            $logs = json_decode(file_get_contents($logsFile), true) ?? [];
            $logs[] = [
                'id' => (count($logs) + 1),
                'usuario' => $u,
                'acao' => 'Gerou key',
                'key' => $key,
                'data' => date("Y-m-d H:i:s"),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'mockapi_status' => $http,
                'mockapi_response' => $resp ?: $curlErr
            ];
            file_put_contents($logsFile, json_encode($logs, JSON_PRETTY_PRINT));

            if ($http >=200 && $http < 300) {
                $msg = "Key gerada e enviada com sucesso: $key";
            } else {
                $msg = "Key gerada: $key (falha ao enviar para API: $curlErr | status $http)";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Painel - BLACK KEYS</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700&display=swap" rel="stylesheet">
<style>
body{background:#000;color:#fff;font-family:Montserrat;text-align:center;margin:0;padding-top:60px;}
.box{width:400px;margin:auto;background:#12000a;padding:25px;border-radius:14px;border:2px solid #ff1744;box-shadow:0 0 20px #ff1744;}
button{padding:12px 25px;background:#ff1744;border:none;border-radius:10px;margin-top:20px;cursor:pointer;font-size:1.1em;}
button:hover{background:#30000a;color:#ff1744;}
input,select{width:90%;padding:10px;margin:8px 0;border-radius:10px;border:none;background:#1a0008;color:#fff;box-shadow:0 0 10px #ff1744aa;}
#hamburger{position:fixed;top:20px;left:20px;font-size:40px;cursor:pointer;display:block;z-index:9999;}
#adminMenu{position:fixed;top:0;left:-260px;width:250px;height:100%;background:#12000a;padding:25px;border-right:2px solid #ff1744;box-shadow:0 0 20px #ff1744;transition:0.3s;z-index:9998;}
#adminMenu h2{color:#ff1744;}#adminMenu a{display:block;margin:15px 0;color:#fff;text-decoration:none;font-size:1.1em;}#adminMenu a:hover{color:#ff1744;}
.msg{color:#00ff00;margin:10px 0;}
.err{color:#ff4c4c;margin:10px 0;}
</style>
</head>
<body>

<!-- hamburger menu visible to all -->
<div id="hamburger">☰</div>
<div id="adminMenu">
    <h2>Menu</h2>
    <?php if($isAdmin): ?>
        <a href="admin.php">Painel Administrativo</a>
    <?php endif; ?>
    <a href="painel.php">Painel do usuário</a>
    <a href="index.php?logout=1" style="color:#ff4d4d;">Sair</a>
</div>

<div class="box">
    <h1 style="color:#ff1744;">Bem-vindo, <?= htmlspecialchars($u) ?></h1>
    <p>Créditos disponíveis: <b><?= htmlspecialchars($users[$u]['creditos'] ?? 0) ?></b></p>

    <!-- only non-admin users see the 'Gerar Key' as user functionality, but admins can also generate here -->
    <h3>Gerar Key</h3>
    <?php if($msg): ?>
        <div class="<?= (strpos($msg,'sucesso')!==false) ? 'msg' : 'err' ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="text" name="prefixo" placeholder="Prefixo (ex: FERRAO-CHEATS)" required>

        <label style="display:block;margin-top:6px;text-align:left;">Validade:</label>
        <select name="validade" required>
            <option value="1D">1 Dia (1)</option>
            <option value="3D">3 Dias (3)</option>
            <option value="7D">7 Dias (7)</option>
            <option value="30D">30 Dias (30)</option>
            <option value="12H">12 Horas (12)</option>
            <option value="24H">24 Horas (24)</option>
        </select>

        <button type="submit" name="gerar">Gerar Key</button>
    </form>

    <button onclick="location.href='index.php?logout=1'">Sair</button>
</div>

<script>
let menu = document.getElementById("adminMenu");
let btn = document.getElementById("hamburger");
if (btn) {
    btn.onclick = () => {
        menu.style.left = (menu.style.left === "0px") ? "-260px" : "0px";
    }
}
</script>

</body>
</html>