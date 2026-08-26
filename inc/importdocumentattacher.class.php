<?php

/**
 * -------------------------------------------------------------------------
 * DataInjection plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of DataInjection.
 *
 * DataInjection is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * DataInjection is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with DataInjection. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2007-2023 by DataInjection plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/datainjection
 * -------------------------------------------------------------------------
 */

class PluginDatainjectionImportDocumentAttacher
{
    public static function attach(
        PluginDatainjectionModel $model,
        PluginDatainjectionBackend $backend,
        array $results,
        string $originalFilename,
        int $entities_id
    ): ?int {
        if (!class_exists('Document') || !class_exists('Document_Item')) {
            return null;
        }

        $targets = self::getSuccessfulTargets($model->getItemtype(), $results);
        if (empty($targets)) {
            return null;
        }

        $source = $backend->getFilePath();
        if (empty($source) || !is_file($source)) {
            return null;
        }

        try {
            $upload = self::copyToUploadDirectory($source, $originalFilename);
            if ($upload === null) {
                self::warnAttachmentFailed();
                return null;
            }

            $documents_id = self::createDocument($model, $upload, $originalFilename, $entities_id);
            if ($documents_id === null) {
                self::cleanupUploadCopy($upload['upload_file']);
                self::warnAttachmentFailed();
                return null;
            }

            self::linkDocumentToTargets($documents_id, $targets, $entities_id);
            return $documents_id;
        } catch (Throwable $e) {
            if (class_exists('ErrorHandler')) {
                ErrorHandler::logCaughtException($e);
            }

            self::warnAttachmentFailed();
            return null;
        }
    }

    private static function getSuccessfulTargets(string $modelItemtype, array $results): array
    {
        $targets = [];
        foreach ($results as $result) {
            if (
                !is_array($result)
                || ($result['status'] ?? null) !== PluginDatainjectionCommonInjectionLib::SUCCESS
            ) {
                continue;
            }

            $target = self::getTargetFromResult($modelItemtype, $result);
            if ($target === null || !self::canAttachDocument($target['itemtype'], $target['items_id'])) {
                continue;
            }

            $targets[$target['itemtype'] . ':' . $target['items_id']] = $target;
        }

        return array_values($targets);
    }

    private static function getTargetFromResult(string $modelItemtype, array $result): ?array
    {
        if (
            isset($result['_datainjection_target']['itemtype'], $result['_datainjection_target']['items_id'])
            && (int) $result['_datainjection_target']['items_id'] > 0
        ) {
            return [
                'itemtype' => $result['_datainjection_target']['itemtype'],
                'items_id' => (int) $result['_datainjection_target']['items_id'],
            ];
        }

        if (!empty($result[$modelItemtype])) {
            return [
                'itemtype' => $modelItemtype,
                'items_id' => (int) $result[$modelItemtype],
            ];
        }

        return null;
    }

    private static function canAttachDocument(string $itemtype, int $items_id): bool
    {
        return $items_id > 0
            && is_a($itemtype, CommonDBTM::class, true)
            && Document::canApplyOn($itemtype);
    }

    private static function copyToUploadDirectory(string $source, string $originalFilename): ?array
    {
        if (!defined('GLPI_UPLOAD_DIR') || !is_dir(GLPI_UPLOAD_DIR) || !is_writable(GLPI_UPLOAD_DIR)) {
            return null;
        }

        $safeFilename = self::sanitizeFilename($originalFilename);
        $prefix = self::makeUploadPrefix();
        $uploadFilename = $prefix . $safeFilename;
        $uploadPath = GLPI_UPLOAD_DIR . '/' . $uploadFilename;

        if (!@copy($source, $uploadPath)) {
            return null;
        }

        return [
            'upload_file' => $uploadFilename,
            'prefix'      => $prefix,
        ];
    }

    private static function createDocument(
        PluginDatainjectionModel $model,
        array $upload,
        string $originalFilename,
        int $entities_id
    ): ?int {
        $document = new Document();
        $documents_id = $document->add([
            'name'                    => self::getDocumentName($model, $originalFilename),
            'entities_id'             => $entities_id,
            'is_recursive'            => 0,
            'upload_file'             => $upload['upload_file'],
            '_prefix_filename'        => [$upload['prefix']],
            '_only_if_upload_succeed' => true,
            'comment'                 => sprintf(
                __('Uploaded by DataInjection model "%1$s" (#%2$s).', 'datainjection'),
                $model->fields['name'] ?? '',
                $model->fields['id'] ?? '',
            ),
        ]);

        return !empty($documents_id) ? (int) $documents_id : null;
    }

    private static function linkDocumentToTargets(int $documents_id, array $targets, int $entities_id): void
    {
        foreach ($targets as $target) {
            if (self::documentLinkExists($documents_id, $target['itemtype'], $target['items_id'])) {
                continue;
            }

            $document_item = new Document_Item();
            $document_item->add([
                'documents_id' => $documents_id,
                'itemtype'     => $target['itemtype'],
                'items_id'     => $target['items_id'],
                'entities_id'  => $entities_id,
                'is_recursive' => 0,
                '_do_notif'    => false,
            ]);
        }
    }

    private static function documentLinkExists(int $documents_id, string $itemtype, int $items_id): bool
    {
        return countElementsInTable(Document_Item::getTable(), [
            'documents_id' => $documents_id,
            'itemtype'     => $itemtype,
            'items_id'     => $items_id,
        ]) > 0;
    }

    private static function cleanupUploadCopy(string $uploadFilename): void
    {
        $uploadPath = GLPI_UPLOAD_DIR . '/' . $uploadFilename;
        if (is_file($uploadPath)) {
            @unlink($uploadPath);
        }
    }

    private static function getDocumentName(PluginDatainjectionModel $model, string $originalFilename): string
    {
        return sprintf(
            __('CSV import - %1$s - %2$s', 'datainjection'),
            $model->fields['name'] ?? '',
            self::sanitizeFilename($originalFilename),
        );
    }

    private static function sanitizeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: '';
        $filename = trim($filename, '._-');
        if ($filename === '') {
            $filename = 'datainjection.csv';
        }

        if (pathinfo($filename, PATHINFO_EXTENSION) === '') {
            $filename .= '.csv';
        }

        if (strlen($filename) <= 180) {
            return $filename;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $suffix = $extension !== '' ? '.' . $extension : '';

        return substr($basename, 0, 180 - strlen($suffix)) . $suffix;
    }

    private static function makeUploadPrefix(): string
    {
        try {
            return 'datainjection_' . bin2hex(random_bytes(8)) . '_';
        } catch (Throwable $e) {
            return str_replace('.', '', uniqid('datainjection_', true)) . '_';
        }
    }

    private static function warnAttachmentFailed(): void
    {
        Session::addMessageAfterRedirect(
            __s(
                'The CSV import finished, but the uploaded CSV could not be attached as a document. Check that CSV is an allowed document type.',
                'datainjection',
            ),
            true,
            WARNING,
        );
    }
}
