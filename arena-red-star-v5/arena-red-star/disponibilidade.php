<?php
require_once 'app.php';
$u = auth();
header('Content-Type: application/json; charset=utf-8');
try {
    $date = $_GET['data'] ?? '';
    if (!validDate($date)) throw new DomainException('Escolha uma data válida.');
    if ($date < date('Y-m-d') || $date > date('Y-m-d', strtotime('+13 days'))) throw new DomainException('A data selecionada está fora do período disponível.');
    $reservas = query("SELECT horario_inicio AS inicio, horario_fim AS fim FROM agendamentos WHERE data=? AND status<>'Cancelado'", [$date])->fetchAll();
    $bloqueios = query('SELECT hora_inicio AS inicio, hora_fim AS fim FROM bloqueios_horarios WHERE data=?', [$date])->fetchAll();
    $ocupados = array_merge($reservas, $bloqueios);
    $horarios = [];
    for ($h = 0; $h <= 22; $h++) {
        $inicio = sprintf('%02d:00', $h);
        if (strtotime($date.' '.$inicio) <= time()) continue;
        $duracoes = [];
        for ($duracao = 1; $duracao <= 23 - $h; $duracao++) {
            $fim = sprintf('%02d:00', $h + $duracao);
            $livre = true;
            foreach ($ocupados as $intervalo) {
                if ($inicio < substr($intervalo['fim'], 0, 5) && $fim > substr($intervalo['inicio'], 0, 5)) { $livre = false; break; }
            }
            if (!$livre) break;
            $duracoes[] = $duracao;
        }
        if ($duracoes) $horarios[] = ['inicio' => $inicio, 'duracoes' => $duracoes];
    }
    echo json_encode(['horarios' => $horarios], JSON_UNESCAPED_UNICODE);
} catch (Throwable $ex) {
    http_response_code(422);
    echo json_encode(['erro' => $ex instanceof DomainException ? $ex->getMessage() : 'Não foi possível carregar os horários.'], JSON_UNESCAPED_UNICODE);
}
