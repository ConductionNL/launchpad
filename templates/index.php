<?php
/**
 * Workspace page template (REQ-SHELL-001).
 *
 * Renders the chrome wrapper `<div id="app-workspace">` and, inside it,
 * the Vue mount point `<div id="workspace-vue">` consumed by `src/main.js`.
 * The chrome wrapper gives `runtime-shell` a stable id Nextcloud's chrome
 * treats as the main content slot.
 *
 * Beside the mount point, the first-paint shell (first-paint-skeleton D1-D4,
 * D7, D8): the page header as text and one frame per widget at its grid
 * position for each breakpoint, with its title and a placeholder shaped like
 * its content, a status line and, should the scripts never take over, a
 * could-not-load line after 15 seconds. It is drawn without script and with
 * its own inline style block, so it paints before any bundle; the mounted
 * page removes it once its grid has laid out (src/utils/firstPaintHandover.js).
 * Every value goes through p().
 *
 * The mount id matches `src/main.js#el` (`#workspace-vue`) and the shell's id
 * matches firstPaintHandover.js (`#launchpad-first-paint`).
 *
 * @category Template
 * @package  OCA\LaunchPad
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec openspec/changes/first-paint-skeleton/tasks.md#task-3
 *
 * SPDX-FileCopyrightText: 2024 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

use OCA\LaunchPad\Service\FirstPaintGreeting;
use OCA\LaunchPad\Service\FirstPaintPlaceholder;

// Nextcloud's template loader hands the parameters over as $_, a name we cannot choose.
$vars = $_; // phpcs:ignore Squiz.NamingConventions.ValidVariableName.NotCamelCaps
$fp   = $vars['firstPaint'] ?? null;
if (is_array($fp) === false) {
	$fp = null;
}
?>

<div id="app-workspace" class="launchpad-workspace">
<?php if (($vars['customCss'] ?? '') !== '') :
	// The global custom CSS (REQ-THM-003) paints with the shell; outside the
	// shell, so the hand-over keeps it, and with the id applyCustomCss reuses.
	// FirstPaintPage::styleText() writes any `<` as a CSS escape.
	?>
	<style id="launchpad-custom-css"><?php print_unescaped((string) $vars['customCss']); ?></style>
<?php endif; ?>
<?php if ($fp !== null) :
	// The column count of each band: wider than 1400 px the dashboard's own,
	// then 12, 8 up to 1100 px and 4 up to 768 px (narrowGridLayout.js).
	$cols  = array_map('intval', $fp['columns']);
	$bands = [$cols[0] ?? 12];
	foreach ([12, 8, 4] as $count) {
		$band = end($bands);
		if (in_array($count, $cols, true) === true) {
			$band = $count;
		}

		$bands[] = $band;
	}

	$rows = [];
	foreach ($bands as $band => $count) {
		$rows[$band] = 1;
		foreach ($fp['frames'] as $frame) {
			$at = $frame['positions'][$count] ?? null;
			if (is_array($at) === true) {
				$rows[$band] = max($rows[$band], $at['y'] + $at['h']);
			}
		}
	}

	$gridStyle = '';
	foreach ($bands as $band => $count) {
		$gridStyle .= '--c'.$band.':'.$count.';--r'.$band.':'.$rows[$band].';';
	}

	// A dashboard that fills the height (LpVandaag): its rows share the
	// height below the header, as CnDashboardGrid's fillRowTracks.
	$fill      = ($fp['fill'] ?? false) === true && ($fp['tracks'] ?? []) !== [];
	$gridClass = 'lp-fp__g';
	if ($fill === true) {
		$gridClass .= ' lp-fp__g--fill';
		$gridStyle .= '--fr:'.implode(' ', $fp['tracks']).';';
	}

	$header = $fp['header'];
	$kind   = '';
	if ($fp['source'] === 'user' && $fp['isDefault'] === true) {
		$kind = $l->t('Personal dashboard · opens first');
	} else if ($fp['source'] === 'user') {
		$kind = $l->t('Personal dashboard');
	} else if ($fp['source'] === 'shared') {
		$kind = $l->t('Shared with you');
	}

	$greeting = $fp['reader'] + ['content' => $header['content'], 'locale' => $l->getLocaleCode()];
	$actions  = static function () use ($l): void {
		?><div class="lp-fp__a">
			<p class="lp-fp__s" role="status"><?php p($l->t('Loading widgets')); ?></p>
			<span class="lp-fp__b" aria-hidden="true">··· <?php p($l->t('More')); ?></span>
		</div><?php
	};
	?>
	<div id="launchpad-first-paint" class="lp-fp">
		<style>
#app-workspace{position:relative;flex:1 1 auto;min-width:0;min-height:calc(100vh - var(--header-height,50px) - var(--body-container-margin,8px))}
.lp-fp{position:absolute;inset:0;z-index:1;overflow:hidden;background:var(--color-main-background-blur,rgba(255,255,255,.8));
color:var(--color-main-text)}
.lp-fp__c{box-sizing:border-box;display:flex;flex-direction:column;height:100%;padding:8px 20px 0;container-type:inline-size;
background:var(--color-background-hover,#f5f5f5)}
.lp-fp__h,.lp-fp__g{flex:none}
.lp-fp__h{display:flex;flex-direction:column;gap:14px;padding:16px 8px 8px}
.lp-fp__r{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-start;gap:12px 24px}
.lp-fp__t{flex:1 1 320px;min-width:0;margin:0;font-size:28px;font-weight:700;line-height:1.2}
.lp-fp__a{display:flex;flex:none;align-items:center;gap:10px;white-space:nowrap}
.lp-fp__b{box-sizing:border-box;display:inline-flex;align-items:center;gap:8px;height:40px;padding:0 16px 0 12px;
border:2px solid var(--color-border-dark,#949494);border-radius:var(--border-radius-element,8px);font-weight:700}
.lp-fp__s{margin:0;font-size:13px;color:var(--color-text-maxcontrast)}
.lp-fp__m{display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px;margin:0;font-size:15px;color:var(--color-text-maxcontrast)}
.lp-fp__k{display:inline-flex;align-items:center;padding:3px 12px;border-radius:var(--border-radius-pill,14px);
background:var(--color-primary-element-light);color:var(--color-primary-element-light-text);font-size:14px;font-weight:600}
.lp-fp__g{position:relative;--c:var(--c0);--r:var(--r0);height:calc(var(--r)*60px)}
.lp-fp__f{position:absolute;box-sizing:border-box;padding:8px;--x:var(--x0);--w:var(--w0);--y:var(--y0);--h:var(--h0);
left:calc(var(--x)*100%/var(--c));width:calc(var(--w)*100%/var(--c));top:calc(var(--y)*60px);height:calc(var(--h)*60px)}
@media (max-width:1400px){.lp-fp__g{--c:var(--c1);--r:var(--r1)}.lp-fp__f{--x:var(--x1);--w:var(--w1);--y:var(--y1);--h:var(--h1)}}
@media (max-width:1100px){.lp-fp__g{--c:var(--c2);--r:var(--r2)}.lp-fp__f{--x:var(--x2);--w:var(--w2);--y:var(--y2);--h:var(--h2)}}
@media (max-width:768px){.lp-fp__g{--c:var(--c3);--r:var(--r3)}.lp-fp__f{--x:var(--x3);--w:var(--w3);--y:var(--y3);
--h:var(--h3)}.lp-fp__f--ph{display:none}}
@container (min-width:960px){.lp-fp__g--fill{display:grid;flex:1 1 auto;min-height:0;height:auto;
grid-template-columns:repeat(var(--c0),minmax(0,1fr));grid-template-rows:var(--fr)}
.lp-fp__g--fill>.lp-fp__f{position:relative;inset:auto;width:auto;height:auto;min-width:0;min-height:0;
grid-column:var(--fc);grid-row:var(--fw)}.lp-fp__g--fill>.lp-fp__f--fit{height:calc(var(--fh)*60px)}}
.lp-fp__d{box-sizing:border-box;display:flex;flex-direction:column;height:100%;overflow:hidden;padding:17px 18px;
border:1px solid var(--sc-work-field,var(--color-border-dark,#949494));border-radius:12px;background:var(--color-main-background)}
.lp-fp__ft{margin:0 0 12px;overflow:hidden;font-size:16px;font-weight:700;line-height:1.2;white-space:nowrap;text-overflow:ellipsis}
.lp-fp__f--heading .lp-fp__d{padding:0;overflow:visible;border:0;border-radius:0;background:none}
.lp-fp__f--heading .lp-fp__ft{margin:-4px 0 0;font-size:20px;line-height:25px}
.lp-fp__f--heading .lp-fp__tx{font-size:14px;line-height:18.2px}
.lp-fp__f--ground .lp-fp__d{display:flex;flex-direction:row;justify-content:space-between;gap:12px 24px;padding:8px 8px 0}
.lp-fp__f--ground h1{margin:0 0 6px;font-size:28px;font-weight:700;line-height:1.2}
.lp-fp__tx{margin:0;font-size:15px;color:var(--color-text-maxcontrast)}
<?php print_unescaped(FirstPaintPlaceholder::css()); ?>
.lp-fp__d>.cn-widget-placeholder{flex:1 1 auto;min-height:0}
.lp-fp__f--tiles .lp-fp__d{padding:0;border:0}
.lp-fp__x{position:absolute;right:28px;bottom:24px;left:28px;margin:0;padding:12px 16px;border-radius:8px;
background:var(--color-main-background);visibility:hidden;animation:lp-fp-x 0s 15s forwards}
@keyframes lp-fp-x{to{visibility:visible}}
@media (prefers-reduced-motion:reduce){.lp-fp__x{animation-duration:0s}}
		</style>
		<div class="lp-fp__c"<?php if (($fp['look'] ?? '') !== '') : ?> style="<?php p((string) $fp['look']); ?>"<?php endif; ?>>
		<?php if ($header['mode'] === 'page') : ?>
			<header class="lp-fp__h">
				<div class="lp-fp__r">
					<h1 class="lp-fp__t"><?php p($header['name']); ?></h1>
					<?php $actions(); ?>
				</div>
				<p class="lp-fp__m">
				<?php if ($kind !== '') : ?>
					<span class="lp-fp__k"><?php p($kind); ?></span>
				<?php endif; ?>
					<span><?php p(trim($header['description']." \u{00a0}")); ?></span>
				</p>
			</header>
		<?php endif; ?>
			<div class="<?php p($gridClass); ?>" style="<?php p($gridStyle); ?>">
			<?php foreach ($fp['frames'] as $frame) :
				$style = '';
				foreach ($bands as $band => $count) {
					$at = $frame['positions'][$count] ?? ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1];
					$style .= '--x'.$band.':'.(int) $at['x'].';--w'.$band.':'.(int) $at['w'].';--y'.$band.':'.(int) $at['y'].';--h'.$band.':'.(int) $at['h'].';';
				}

				if ($fill === true) {
					$cell   = $frame['cell'];
					$style .= '--fc:'.($cell['x'] + 1).'/span '.$cell['w'].';--fw:'.($cell['y'] + 1).'/span '.$cell['h'].';--fh:'.$cell['h'].';';
				}

				$ground  = $header['mode'] === 'greeting' && $frame['id'] === $header['groundId'];
				$classes = 'lp-fp__f lp-fp__f--'.$frame['kind'];
				if ($ground === true) {
					$classes .= ' lp-fp__f--ground';
				}

				if ($frame['phoneHidden'] === true) {
					$classes .= ' lp-fp__f--ph';
				}

				if ($fill === true && $frame['fitRows'] === true) {
					$classes .= ' lp-fp__f--fit';
				}

				$height = (int) ($frame['positions'][$cols[0] ?? 12]['h'] ?? 2) * 60;
				?>
				<div class="<?php p($classes); ?>" data-placement-id="<?php p($frame['id']); ?>" style="<?php p($style); ?>">
					<div class="lp-fp__d">
					<?php if ($ground === true) : ?>
						<div>
							<h1><?php p($header['title']); ?></h1>
							<p class="lp-fp__tx"><?php p(FirstPaintGreeting::line(l: $l, input: $greeting)); ?></p>
						</div>
						<?php $actions(); ?>
					<?php else : ?>
						<?php if ($frame['title'] !== '') : ?>
							<p class="lp-fp__ft"><?php p($frame['title']); ?></p>
						<?php endif; ?>
						<?php if ($frame['kind'] === 'heading') : ?>
							<?php if ($frame['text'] !== '') : ?>
								<p class="lp-fp__tx"><?php p($frame['text']); ?></p>
							<?php endif; ?>
						<?php else : ?>
							<?php print_unescaped(FirstPaintPlaceholder::markup(shape: $frame['kind'], height: ($height - 72))); ?>
						<?php endif; ?>
					<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
			</div>
		</div>
		<p class="lp-fp__x" role="alert">
			<?php p($l->t('The page could not load')); ?>
			<a href="<?php p((string) ($vars['reloadUrl'] ?? '')); ?>"><?php p($l->t('Reload')); ?></a>
		</p>
	</div>
<?php endif; ?>
	<div id="workspace-vue"></div>
</div>
