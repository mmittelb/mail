<?php

declare(strict_types=1);

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * Mail
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Mail\Contracts;

use OCA\Mail\Account;
use OCA\Mail\Exception\ClientException;
use OCA\Mail\Exception\ServiceException;
use OCA\Mail\Folder;
use OCA\Mail\IMAP\FolderStats;
use OCA\Mail\IMAP\Sync\Request as SyncRequest;
use OCA\Mail\IMAP\Sync\Response as SyncResponse;

interface IMailManager {

	/**
	 * @param Account $account
	 * @return Folder[]
	 */
	public function getFolders(Account $account): array;

	/**
	 * @param Account $account
	 * @param string $name
	 *
	 * @return Folder
	 */
	public function createFolder(Account $account, string $name): Folder;

	/**
	 * @param Account $account
	 * @param string $folderId
	 *
	 * @return FolderStats
	 */
	public function getFolderStats(Account $account, string $folderId): FolderStats;

	/**
	 * @param Account
	 * @param SyncRequest $syncRequest
	 * @return SyncResponse
	 *
	 * @throws ClientException
	 * @throws ServiceException
	 */
	public function syncMessages(Account $account, SyncRequest $syncRequest): SyncResponse;

	/**
	 * @param Account $sourceAccount
	 * @param string $sourceFolderId
	 * @param int $messageId
	 * @param Account $destinationAccount
	 * @param string $destFolderId
	 *
	 * @throws ServiceException
	 */
	public function moveMessage(Account $sourceAccount, string $sourceFolderId, int $messageId,
								Account $destinationAccount, string $destFolderId);

	/**
	 * @param Account $account
	 * @param string $mailboxId
	 * @param int $messageId
	 *
	 * @throws ServiceException
	 */
	public function deleteMessage(Account $account, string $mailboxId, int $messageId): void;

	/**
	 * Mark all messages of a folder as read
	 *
	 * @param Account $account
	 * @param string $folderId
	 */
	public function markFolderAsRead(Account $account, string $folderId): void;

}
