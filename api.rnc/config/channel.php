<?php

/*
 * The RNC channel's defaults (rnc-3 R6, D6): what a new channel firm starts with. Each firm stores
 * its own fee and share, so changing these moves no firm already on the board. Money is integer
 * cents and a rate is basis points (plan P5): 350000 = R$ 3.500,00 a month, 1500 = 15 %.
 */
return [
    'monthly_fee_cents' => 350000,
    'share_bp' => 1500,
];
