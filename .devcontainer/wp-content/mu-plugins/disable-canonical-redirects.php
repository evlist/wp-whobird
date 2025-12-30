<?php

// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later OR MIT

/**
 * Dev-only: disable WordPress canonical redirects to avoid "-443" hops in Codespaces.
 */

add_filter('redirect_canonical', '__return_false', 100);