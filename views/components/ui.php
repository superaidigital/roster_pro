<?php
/**
 * Roster Pro Design System v2 — Reusable PHP View Components
 * Pure rendering helpers only. No database access.
 */

if (!function_exists('rp_e')) {
    function rp_e($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rp_component_tone')) {
    function rp_component_tone(string $tone): string {
        $allowed = ['primary', 'success', 'warning', 'danger', 'info', 'violet', 'neutral'];
        return in_array($tone, $allowed, true) ? $tone : 'primary';
    }
}

if (!function_exists('rp_page_header')) {
    function rp_page_header(string $title, string $subtitle = '', string $actionsHtml = '', string $eyebrow = ''): void {
        ?>
        <header class="rp-page-header">
            <div class="rp-page-header__copy">
                <?php if ($eyebrow !== ''): ?>
                    <p class="rp-page-header__eyebrow"><?= rp_e($eyebrow) ?></p>
                <?php endif; ?>
                <h1 class="rp-page-header__title"><?= rp_e($title) ?></h1>
                <?php if ($subtitle !== ''): ?>
                    <p class="rp-page-header__subtitle"><?= rp_e($subtitle) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($actionsHtml !== ''): ?>
                <div class="rp-page-header__actions"><?= $actionsHtml ?></div>
            <?php endif; ?>
        </header>
        <?php
    }
}

if (!function_exists('rp_section_header')) {
    function rp_section_header(string $title, string $description = '', string $actionsHtml = ''): void {
        ?>
        <div class="rp-section-header">
            <div>
                <h2 class="rp-section-title"><?= rp_e($title) ?></h2>
                <?php if ($description !== ''): ?>
                    <p class="rp-section-description"><?= rp_e($description) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($actionsHtml !== ''): ?>
                <div class="rp-cluster"><?= $actionsHtml ?></div>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('rp_stat_card')) {
    function rp_stat_card(
        string $label,
        $value,
        string $icon,
        string $tone = 'primary',
        string $href = '',
        string $meta = ''
    ): void {
        $tone = rp_component_tone($tone);
        $toneClass = $tone === 'primary' ? '' : ' rp-stat-card--' . $tone;
        $content = '
            <div class="rp-card rp-card--interactive rp-stat-card' . $toneClass . '">
                <div class="rp-stat-card__copy">
                    <p class="rp-stat-card__label">' . rp_e($label) . '</p>
                    <p class="rp-stat-card__value">' . rp_e($value) . '</p>
                    ' . ($meta !== '' ? '<div class="rp-stat-card__meta">' . rp_e($meta) . '</div>' : '') . '
                </div>
                <span class="rp-stat-card__icon" aria-hidden="true">
                    <i class="bi ' . rp_e($icon) . '"></i>
                </span>
            </div>';

        if ($href !== '') {
            echo '<a class="rp-card-link" href="' . rp_e($href) . '">' . $content . '</a>';
        } else {
            echo $content;
        }
    }
}

if (!function_exists('rp_action_card')) {
    function rp_action_card(
        string $title,
        string $subtitle,
        string $icon,
        string $href,
        string $tone = 'primary',
        ?int $badge = null
    ): void {
        $tone = rp_component_tone($tone);
        $toneClass = $tone === 'primary' ? '' : ' rp-action-card--' . $tone;
        ?>
        <a class="rp-action-card<?= $toneClass ?>" href="<?= rp_e($href) ?>">
            <span class="rp-action-card__icon" aria-hidden="true"><i class="bi <?= rp_e($icon) ?>"></i></span>
            <span class="rp-action-card__copy">
                <span class="rp-action-card__title"><?= rp_e($title) ?></span>
                <span class="rp-action-card__subtitle"><?= rp_e($subtitle) ?></span>
            </span>
            <?php if ($badge !== null && $badge > 0): ?>
                <span class="rp-action-card__badge" aria-label="<?= rp_e($badge . ' รายการรอดำเนินการ') ?>"><?= rp_e($badge > 99 ? '99+' : $badge) ?></span>
            <?php endif; ?>
        </a>
        <?php
    }
}

if (!function_exists('rp_empty_state')) {
    function rp_empty_state(
        string $icon,
        string $title,
        string $description,
        string $ctaLabel = '',
        string $ctaHref = ''
    ): void {
        ?>
        <div class="rp-empty-state">
            <div class="rp-empty-state__icon" aria-hidden="true"><i class="bi <?= rp_e($icon) ?>"></i></div>
            <h3 class="rp-empty-state__title"><?= rp_e($title) ?></h3>
            <p class="rp-empty-state__description"><?= rp_e($description) ?></p>
            <?php if ($ctaLabel !== '' && $ctaHref !== ''): ?>
                <a class="rp-btn rp-btn--secondary mt-3" href="<?= rp_e($ctaHref) ?>"><?= rp_e($ctaLabel) ?></a>
            <?php endif; ?>
        </div>
        <?php
    }
}
