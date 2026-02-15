INSERT IGNORE INTO form_version (form_version_id, name, is_active) VALUES (1, 'analysis_note_ver1', 1);

INSERT IGNORE INTO step_definition (form_version_id, step_key, label, category, sort_order, is_active) VALUES
(1, 'LO', 'Lineout', 'phase', 10, 1),
(1, 'LOM', 'Lineout Move', 'phase', 20, 1),
(1, 'RK', 'Ruck', 'phase', 30, 1),
(1, 'AT', 'Attack', 'phase', 40, 1),
(1, 'DF', 'Defense', 'phase', 50, 1),
(1, 'TRY', 'Try', 'outcome', 110, 1),
(1, 'KN', 'Knock-on', 'outcome', 120, 1),
(1, 'PEN', 'Penalty', 'outcome', 130, 1);

INSERT IGNORE INTO choice_definition (form_version_id, choice_key, label, category, sort_order, is_active) VALUES
(1, 'WIDE', '外展開', 'attack', 10, 1),
(1, 'PICK', 'ピック&ゴー', 'attack', 20, 1),
(1, 'KICK', 'キック', 'attack', 30, 1),
(1, 'RESET', '再セット', 'defense', 40, 1);
