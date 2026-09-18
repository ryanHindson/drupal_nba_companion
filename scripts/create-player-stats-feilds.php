<?php

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

$bundle = 'player_season_stats';

if (!\Drupal\node\Entity\NodeType::load($bundle)) {
  throw new \RuntimeException(
    'Player Season Stats content type was not found.'
  );
}

$columns = preg_split('/\s+/', trim(<<<'COLUMNS'
AGE GP W L W_PCT MIN
FGM FGA FG_PCT FG3M FG3A FG3_PCT
FTM FTA FT_PCT
OREB DREB REB AST TOV STL BLK BLKA PF PFD
PTS PLUS_MINUS NBA_FANTASY_PTS DD2 TD3
GP_RANK W_RANK L_RANK W_PCT_RANK MIN_RANK
FGM_RANK FGA_RANK FG_PCT_RANK
FG3M_RANK FG3A_RANK FG3_PCT_RANK
FTM_RANK FTA_RANK FT_PCT_RANK
OREB_RANK DREB_RANK REB_RANK AST_RANK TOV_RANK
STL_RANK BLK_RANK BLKA_RANK PF_RANK PFD_RANK
PTS_RANK PLUS_MINUS_RANK NBA_FANTASY_PTS_RANK
DD2_RANK TD3_RANK
COLUMNS));

foreach ($columns as $column) {
  $field_name = 'field_ps_' . strtolower($column);

  $is_integer = in_array(
    $column,
    ['GP', 'W', 'L', 'DD2', 'TD3'],
    TRUE
  ) || str_ends_with($column, '_RANK');

  $type = $is_integer ? 'integer' : 'decimal';
  $existing_storage = FieldStorageConfig::loadByName(
    'node',
    $field_name
  );

  if (!$existing_storage) {
    $definition = [
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => $type,
    ];

    if (!$is_integer) {
      $definition['settings'] = [
        'precision' => 12,
        'scale' => 3,
      ];
    }

    FieldStorageConfig::create($definition)->save();
  }
  elseif ($existing_storage->getType() !== $type) {
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