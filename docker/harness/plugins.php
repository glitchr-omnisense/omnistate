<?php

/**
 * Every source package: its slug (omnistate/<slug>, github.com/glitchr-studio/omnistate-<slug>),
 * its tests' namespace, its registry class and what it is a registry of -
 * the argument of Omnistate\Omnistate it is given as.
 */
return [
    'annuaire-entreprises' => ['Omnistate\\AnnuaireEntreprises\\Tests\\', 'Omnistate\\AnnuaireEntreprises\\AnnuaireEntreprises', 'companies'],
    'vies' => ['Omnistate\\Vies\\Tests\\', 'Omnistate\\Vies\\Vies', 'vat'],
    'iana' => ['Omnistate\\Iana\\Tests\\', 'Omnistate\\Iana\\Rdap', 'internet'],
    'annuaire-sante' => ['Omnistate\\AnnuaireSante\\Tests\\', 'Omnistate\\AnnuaireSante\\AnnuaireSante', 'professionals'],
    'matchid' => ['Omnistate\\MatchId\\Tests\\', 'Omnistate\\MatchId\\MatchId', 'civil'],
    'openarchieven' => ['Omnistate\\OpenArchieven\\Tests\\', 'Omnistate\\OpenArchieven\\OpenArchieven', 'civil'],
    'national-archives-uk' => ['Omnistate\\NationalArchivesUk\\Tests\\', 'Omnistate\\NationalArchivesUk\\NationalArchivesUk', 'civil'],
    'nara' => ['Omnistate\\Nara\\Tests\\', 'Omnistate\\Nara\\Nara', 'civil'],
];
