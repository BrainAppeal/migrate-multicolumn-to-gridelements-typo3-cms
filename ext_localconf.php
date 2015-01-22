<?php
if (TYPO3_MODE === 'BE') {
    $tsIncludeConstants = "<INCLUDE_TYPOSCRIPT: source=FILE:EXT:$_EXTKEY/Configuration/TypoScript/constants.txt>";
$tsIncludeSetup = "<INCLUDE_TYPOSCRIPT: source=FILE:EXT:$_EXTKEY/Configuration/TypoScript/setup.txt>";
    t3lib_extMgm::addTypoScript($_EXTKEY, 'constants', $tsIncludeConstants);
    t3lib_extMgm::addTypoScript($_EXTKEY, 'setup', $tsIncludeSetup);
}
