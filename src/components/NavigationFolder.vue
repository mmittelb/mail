<!--
  - @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
  -
  - @author 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -->

<template>
	<AppNavigationItem :item="data" :menu-open.sync="menuOpen" />
</template>

<script>
import AppNavigationItem from 'nextcloud-vue/dist/Components/AppNavigationItem'

import {getFolderStats} from '../service/FolderService'
import Logger from '../logger'
import {translate as translateMailboxName} from '../l10n/MailboxTranslator'

export default {
	name: 'NavigationFolder',
	components: {
		AppNavigationItem,
	},
	props: {
		account: {
			type: Object,
			required: true,
		},
		folder: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			menuOpen: false,
			loadingFolderStats: true,
			folderStats: undefined,
		}
	},
	computed: {
		data() {
			return this.folderToEntry(this.folder, true)
		},
	},
	watch: {
		menuOpen() {
			// Fetch current stats when the menu is opened
			if (this.menuOpen) {
				this.fetchFolderStats()
			}
		},
	},
	methods: {
		folderToEntry(folder, top) {
			let icon = 'folder'
			if (folder.specialRole) {
				icon = folder.specialRole
			}

			const actions = []

			if (top) {
				if (this.loadingFolderStats) {
					actions.push({
						icon: 'icon-info',
						text: atob(this.folder.id),
						longtext: t('mail', 'Loading …'),
					})
				} else {
					actions.push({
						icon: 'icon-info',
						text: atob(this.folder.id),
						longtext: t('mail', '{total} messages ({unread} unread)', {
							total: this.folderStats.total,
							unread: this.folderStats.unread,
						}),
					})
				}
			}

			if (top) {
				// TODO: make *mark as read* available for all folders once there is
				//       more than one action
				actions.push({
					icon: 'icon-checkmark',
					text: t('mail', 'Mark all as read'),
					longtext: t('mail', 'Mark all messages of this folder as read'),
					action: this.markAsRead(folder),
				})

				actions.push({
					icon: 'icon-add',
					text: t('mail', 'Add subfolder'),
					input: 'text',
					action: this.createFolder,
				})
			}

			return {
				id: 'account' + this.account.id + '_' + folder.id,
				key: 'account' + this.account.id + '_' + folder.id,
				text: translateMailboxName(folder),
				icon: 'icon-' + icon,
				router: {
					name: 'folder',
					params: {
						accountId: this.account.id,
						folderId: folder.id,
					},
					exact: false,
				},
				utils: {
					actions,
					counter: folder.unread,
				},
				collapsible: true,
				opened: folder.opened,
				children: this.$store.getters
					.getSubfolders(this.account.id, folder.id)
					.map(folder => this.folderToEntry(folder, false)),
			}
		},
		fetchFolderStats() {
			this.loadingFolderStats = true

			getFolderStats(this.account.id, this.folder.id)
				.then(stats => {
					Logger.debug('loaded folder stats', {stats})
					this.folderStats = stats

					this.loadingFolderStats = false
				})
				.catch(error => Logger.error(`could not load folder states for ${this.folder.id}`, {error}))
		},
		createFolder(e) {
			const name = e.target.elements[0].value
			const withPrefix = atob(this.folder.id) + this.folder.delimiter + name
			Logger.info(`creating folder ${withPrefix} as subfolder of ${this.folder.id}`)
			this.menuOpen = false
			this.$store
				.dispatch('createFolder', {account: this.account, name: withPrefix})
				.then(() => Logger.info(`folder ${withPrefix} created`))
				.catch(error => {
					Logger.error(`could not create folder ${withPrefix}`, {error})
					throw error
				})
		},
		markAsRead(folder) {
			return () => {
				this.menuOpen = false
				this.$store
					.dispatch('markFolderRead', {account: this.account, folderId: folder.id})
					.then(() => Logger.info(`folder ${folder.id} marked as read`))
					.catch(error => Logger.error(`could not mark folder ${folder.id} as read`, {error}))
			}
		},
	},
}
</script>
