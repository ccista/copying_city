<?php
if ($modx->event->name !== 'OnDocFormSave') {
    return;
}

if ((int) $resource->get('id') !== 4) {
    return;
}

$newcity = trim((string) $resource->getTVValue('newcity'));

if ($newcity === '') {
    return;
}

// Проверяем, существует ли город.
$existingCity = $modx->getObject('modResource', [
    'parent' => 13,
    'pagetitle' => $newcity,
]);

if ($existingCity) {
    $resource->setTVValue('newcity', '');
    return;
}

// Замыкание: не вызывает ошибку повторного объявления функции.
$copyResourceRecursively = function (
    $sourceId,
    $newParentId,
    $newTitle = null
) use ($modx, &$copyResourceRecursively) {
    $source = $modx->getObject('modResource', (int) $sourceId);

    if (!$source) {
        $modx->log(
            modX::LOG_LEVEL_ERROR,
            'Копирование города: не найден ресурс ' . $sourceId
        );
        return false;
    }

    $fields = $source->toArray();

    unset($fields['id']);

    $fields['parent'] = (int) $newParentId;
    $fields['createdon'] = time();

    // Сохраняем alias и шаблон исходного ресурса.
    $fields['alias'] = $source->get('alias');
    $fields['template'] = $source->get('template');

    // Старый полный путь не переносим.
    $fields['uri'] = '';
    $fields['uri_override'] = 0;

    // Только главную страницу переименовываем в город.
    if ($newTitle !== null) {
        $fields['alias'] = $source->cleanAlias($newTitle);
        $fields['pagetitle'] = $newTitle;
        $fields['alias_visible'] = 1;

        // Как в исходном плагине: контент главной не копируем.
        unset($fields['content']);
    }

    $new = $modx->newObject('modResource');
    $new->fromArray($fields, '', true);

    if (!$new->save()) {
        $modx->log(
            modX::LOG_LEVEL_ERROR,
            'Копирование города: ошибка сохранения копии ресурса ' . $sourceId
        );
        return false;
    }

    $newId = (int) $new->get('id');

    // ID исходного ресурса — для города и всех его потомков.
    if (!$new->setTVValue('idContent', (int) $sourceId)) {
        $modx->log(
            modX::LOG_LEVEL_ERROR,
            'Копирование города: не удалось записать idContent ресурсу ' . $newId
        );
        return false;
    }

    // Копируем все уровни вложенности.
    $children = $modx->getIterator('modResource', [
        'parent' => (int) $sourceId,
    ]);

    foreach ($children as $child) {
        $result = $copyResourceRecursively(
            (int) $child->get('id'),
            $newId
        );

        if ($result === false) {
            return false;
        }
    }

    return $newId;
};

// Главная страница 1 → папка «Города» 13.
$newCityId = $copyResourceRecursively(1, 13, $newcity);

if ($newCityId !== false) {
    $resource->setTVValue('newcity', '');

    $modx->log(
        modX::LOG_LEVEL_INFO,
        'Создан город «' . $newcity . '», ID: ' . $newCityId
    );
}
