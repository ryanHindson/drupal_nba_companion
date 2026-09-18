<?php

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

$bundle = 'team_season_stats';

if (!\Drupal\node\Entity\NodeType::load($bundle)) {
  throw new \RuntimeException(
    'Create the Team Season Stats content type before running this script.'
  );
}

$columns = preg_split('/\s+/', trim(<<<'COLUMNS'
GP W L W_PCT MIN
FGM FGA FG_PCT FG3M FG3A FG3_PCT
FTM FTA FT_PCT
OREB DREB REB AST TOV STL BLK BLKA PF PFD PTS PLUS_MINUS
GP_RANK W_RANK L_RANK W_PCT_RANK MIN_RANK
FGM_RANK FGA_RANK FG_PCT_RANK
FG3M_RANK FG3A_RANK FG3_PCT_RANK
FTM_RANK FTA_RANK FT_PCT_RANK
OREB_RANK DREB_RANK REB_RANK AST_RANK TOV_RANK
STL_RANK BLK_RANK BLKA_RANK PF_RANK PFD_RANK
PTS_RANK PLUS_MINUS_RANK
COLUMNS));

foreach ($columns as $column) {
  $field_name = 'field_stat_' . strtolower($column);

  // Season totals and rankings are integers; per-game values are decimals.
  $is_integer = in_array($column, ['GP', 'W', 'L'], TRUE)
    || str_ends_with($column, '_RANK');

  $existing_storage = FieldStorageConfig::loadByName('node', $field_name);

  if (!$existing_storage) {
    $definition = [
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => $is_integer ? 'integer' : 'decimal',
    ];

    if (!$is_integer) {
      $definition['settings'] = [
        'precision' => 12,
        'scale' => 3,
      ];
    }

    FieldStorageConfig::create($definition)->save();
  }
  elseif ($existing_storage->getType() !== ($is_integer ? 'integer' : 'decimal')) {
    throw new \RuntimeException(
      "Existing field $field_name has an unexpected type."
    );
  }

  if (!FieldConfig::loadByName('node', $bundle, $field_name)) {
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => $bundle,
      'label' => str_replace('_', ' ', $column),
      'required' => FALSE,
    ])->save();

    echo "Created $field_name\n";
  }
  else {
    echo "Already exists: $field_name\n";
  }
}