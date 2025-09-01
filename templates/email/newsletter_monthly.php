<?php
$subject = "Newsletter mensuelle Driv'n Cook - " . date('F Y');
$html = '';
$text = '';
?>

<?php ob_start(); ?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter Driv'n Cook</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
        }
        .header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .content {
            padding: 30px 20px;
        }
        .menu-item {
            border: 1px solid #eee;
            border-radius: 8px;
            margin: 15px 0;
            padding: 15px;
        }
        .price {
            color: #28a745;
            font-weight: bold;
            font-size: 18px;
        }
        .button {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Driv'n Cook</h1>
            <p>Newsletter de <?php echo $data['month_name'] ?? date('F Y'); ?></p>
        </div>

        <div class="content">
            <h2>Bonjour <?php echo $data['firstname'] ?? 'Cher client'; ?> !</h2>

            <p>Découvrez les nouveautés de ce mois chez Driv'n Cook :</p>

            <?php if (!empty($data['new_menus'])): ?>
                <h3>Nouveaux menus</h3>
                <?php foreach ($data['new_menus'] as $menu): ?>
                    <div class="menu-item">
                        <h4><?php echo htmlspecialchars($menu['name']); ?></h4>
                        <p><?php echo htmlspecialchars($menu['description']); ?></p>
                        <div class="price"><?php echo number_format($menu['price'], 2); ?> €</div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($data['upcoming_events'])): ?>
                <h3>Événements à venir</h3>
                <?php foreach ($data['upcoming_events'] as $event): ?>
                    <div class="menu-item">
                        <h4><?php echo htmlspecialchars($event['title']); ?></h4>
                        <p><strong>📍 <?php echo htmlspecialchars($event['location']); ?></strong></p>
                        <p>🗓️ <?php echo date('d/m/Y', strtotime($event['event_date'])); ?>
                            <?php if ($event['event_time']): ?>
                                à <?php echo date('H:i', strtotime($event['event_time'])); ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($event['price'] > 0): ?>
                            <div class="price"><?php echo number_format($event['price'], 2); ?> €</div>
                        <?php else: ?>
                            <div class="price" style="color: #17a2b8;">Gratuit</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($data['discount_offer'])): ?>
                <div
                    style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #856404;">🎉 Offre spéciale</h3>
                    <p style="color: #856404;"><?php echo htmlspecialchars($data['discount_offer']); ?></p>
                </div>
            <?php endif; ?>

            <div style="text-align: center; margin: 30px 0;">
                <a href="https://drivinCook.fr/client/menu.php" class="button">
                    Voir notre menu complet
                </a>
                <a href="https://drivinCook.fr/client/events.php" class="button" style="background: #007bff;">
                    Nos événements
                </a>
            </div>
        </div>

        <div class="footer">
            <p><strong>Driv'n Cook</strong> - Des food trucks de qualité</p>
            <p>📞 01 23 45 67 89 | 📧 contact@drivinCook.fr</p>
            <p style="font-size: 12px; margin-top: 15px;">
                Vous recevez cet email car vous êtes abonné à notre newsletter.<br>
                <a href="https://drivinCook.fr/client/unsubscribe.php?email={{email}}">Se désabonner</a>
            </p>
        </div>
    </div>
</body>

</html>
<?php
$html = ob_get_clean();

ob_start();
?>
NEWSLETTER DRIV'N COOK - <?php echo date('F Y'); ?>

Bonjour <?php echo $data['firstname'] ?? 'Cher client'; ?> !

Découvrez les nouveautés de ce mois chez Driv'n Cook :

<?php if (!empty($data['new_menus'])): ?>
    NOUVEAUX MENUS :
    <?php foreach ($data['new_menus'] as $menu): ?>
        - <?php echo $menu['name']; ?> - <?php echo number_format($menu['price'], 2); ?>€
        <?php echo $menu['description']; ?>

    <?php endforeach; endif; ?>

<?php if (!empty($data['upcoming_events'])): ?>
    ÉVÉNEMENTS À VENIR :
    <?php foreach ($data['upcoming_events'] as $event): ?>
        - <?php echo $event['title']; ?>
        📍 <?php echo $event['location']; ?>
        🗓️ <?php echo date('d/m/Y', strtotime($event['event_date'])); ?>
        <?php echo $event['price'] > 0 ? number_format($event['price'], 2) . '€' : 'Gratuit'; ?>

    <?php endforeach; endif; ?>

<?php if (!empty($data['discount_offer'])): ?>
    OFFRE SPÉCIALE :
    <?php echo $data['discount_offer']; ?>
<?php endif; ?>

Visitez notre site : https://drivinCook.fr
Téléphone : 01 23 45 67 89
Email : contact@drivinCook.fr

---
Vous recevez cet email car vous êtes abonné à notre newsletter.
Pour vous désabonner : https://drivinCook.fr/client/unsubscribe.php?email={{email}}
<?php
$text = ob_get_clean();

return [
    'subject' => $subject,
    'html' => $html,
    'text' => $text
];
?>