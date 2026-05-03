<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sendent B.V.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\SendentWorkspace\Settings\Admin;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class Section implements IIconSection {

	public function __construct(
		private IL10N $l,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getID(): string {
		return 'sendentworkspace';
	}

	public function getName(): string {
		return $this->l->t('Sendent Workspace');
	}

	public function getPriority(): int {
		return 75;
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath('sendentworkspace', 'app-dark.svg');
	}
}
