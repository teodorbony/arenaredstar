<?php
require_once 'app.php';
$config = require 'arena_config.php';
$summary = ['media' => null, 'total' => 0];
$reviews = [];
try {
    $summary = query('SELECT AVG(nota) media,COUNT(*) total FROM avaliacoes')->fetch();
    $reviews = query('SELECT a.nota,a.comentario,a.data_avaliacao,u.nome FROM avaliacoes a JOIN usuarios u ON u.id=a.usuario_id ORDER BY a.data_avaliacao DESC,a.id DESC LIMIT 6')->fetchAll();
} catch (PDOException $ex) {
    error_log($ex->getMessage());
}
pageStart('Início');
?>
<section class="landing-hero">
    <div><div class="panel-kicker">ARENA RED STAR POINT BALL</div><h1>Mais que futebol,<br><span>é resenha!</span></h1><p>Reserve nosso campo society, reúna os amigos e venha jogar na Arena Red Star.</p><div class="hero-price">R$ 120,00 <small>por hora</small></div><div class="hero-buttons"><a class="primary-btn" href="<?= isset($_SESSION['usuario_id']) ? 'usuario.php#agendar' : 'login.php' ?>">Agendar agora →</a><a class="outline-btn" href="#galeria">Conheça a Arena</a><a class="outline-btn" href="#avaliacoes">Avaliações</a></div><p class="muted"><?= $config['funcionamento'] !== '' ? 'Funcionamento: '.e($config['funcionamento']) : 'Consulte a disponibilidade no calendário ao entrar.' ?></p></div>
    <img class="landing-logo" src="assets/logo-red-star.png" alt="Logo da Arena Red Star">
</section>

<section class="benefit-row"><article><?= rs_icon('ball') ?> <div><strong>Campo society</strong><small>Reserve seu horário online</small></div></article><article><?= rs_icon('users') ?> <div><strong>Chame sua equipe</strong><small>Futebol e confraternização</small></div></article><article><?= rs_icon('ticket') ?> <div><strong>Boas-vindas</strong><small>Novas contas ganham REDSTAR10</small></div></article></section>

<section id="galeria" class="landing-section">
    <div class="panel-kicker">NOSSA ESTRUTURA</div><h2>Conheça nossa Arena</h2>
    <div class="gallery-grid"><?php foreach ($config['galeria'] as $photo): ?><figure><?php if (is_file(__DIR__.'/'.$photo['arquivo'])): ?><img src="<?= e($photo['arquivo']) ?>" alt="<?= e($photo['titulo']) ?>" loading="lazy"><?php else: ?><div class="photo-placeholder"><span><?= rs_icon('ball') ?></span><p>Foto em breve</p></div><?php endif; ?><figcaption><?= e($photo['titulo']) ?></figcaption></figure><?php endforeach; ?></div>
    <div class="location-card">
        <div class="location-copy"><div class="panel-kicker">COMO CHEGAR</div><h3>Arena Red Star — Criciúma</h3><p>Rodovia José Spillere, Criciúma - SC</p><p class="muted">Abra a rota no celular ou consulte a localização diretamente no mapa.</p><a class="primary-btn" href="https://www.google.com/maps/search/?api=1&query=Arena+Red+Star+Rodovia+Jos%C3%A9+Spillere+Crici%C3%BAma+SC" target="_blank" rel="noopener">Abrir rota no Google Maps →</a></div>
        <div class="map-frame"><iframe title="Localização da Arena Red Star em Criciúma" src="https://www.google.com/maps?q=Arena%20Red%20Star%20Rodovia%20Jos%C3%A9%20Spillere%2C%20Crici%C3%BAma%20-%20SC&output=embed" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe></div>
    </div>
</section>

<section id="avaliacoes" class="landing-section"><div class="section-heading"><div><div class="panel-kicker">QUEM JÁ JOGOU</div><h2>Avaliações da Arena</h2></div><div class="rating-summary"><strong><?= $summary['total'] ? number_format((float)$summary['media'], 1, ',', '.') : '—' ?> ★</strong><span><?= (int)$summary['total'] ?> avaliação(ões)</span></div></div><div class="reviews-grid"><?php if (!$reviews): ?><p class="muted">As avaliações dos clientes aparecerão aqui.</p><?php endif; foreach ($reviews as $r): ?><article class="review-card"><div class="stars" aria-label="<?= (int)$r['nota'] ?> de 5 estrelas"><?= str_repeat('★', (int)$r['nota']).str_repeat('☆', 5-(int)$r['nota']) ?></div><?php if ($r['comentario']): ?><p><?= nl2br(e($r['comentario'])) ?></p><?php endif; ?><small><?= e(explode(' ', trim($r['nome']))[0]) ?> · <?= date('d/m/Y', strtotime($r['data_avaliacao'])) ?></small></article><?php endforeach; ?></div></section>
<?php pageEnd(); ?>
