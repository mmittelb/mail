<!--
  - @copyright 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
  -
  - @author 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
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
	<div class="attachment" @click="download">
		<img v-if="isImage" class="mail-attached-image" :src="url" />
		<img class="attachment-icon" :src="mimeUrl" />
		<span class="attachment-name" :title="label"
			>{{ fileName }}
			<span class="attachment-size">({{ humanReadable(size) }})</span>
		</span>
		<button
			v-if="isCalendarEvent"
			class="button attachment-import calendar"
			:class="{'icon-add': !loadingCalendars, 'icon-loading-small': loadingCalendars}"
			:disabled="loadingCalendars"
			:title="t('mail', 'Import into calendar')"
			@click.stop="loadCalendars"
		></button>
		<button class="button icon-download attachment-download" :title="t('mail', 'Download attachment')"></button>
		<button
			class="attachment-save-to-cloud"
			:class="{'icon-folder': !savingToCloud, 'icon-loading-small': savingToCloud}"
			:disabled="savingToCloud"
			:title="t('mail', 'Save to Files')"
			@click.stop="saveToCloud"
		></button>
		<div
			v-on-click-outside="closeCalendarPopover"
			class="popovermenu bubble attachment-import-popover hidden"
			:class="{open: showCalendarPopover}"
		>
			<PopoverMenu :menu="calendarMenuEntries" />
		</div>
	</div>
</template>

<script>
import {formatFileSize} from 'nextcloud-files'
import {mixin as onClickOutside} from 'vue-on-click-outside'
import {getFilePickerBuilder} from 'nextcloud-dialogs'
import PopoverMenu from 'nextcloud-vue/dist/Components/PopoverMenu'

import {parseUid} from '../util/EnvelopeUidParser'
import Logger from '../logger'

import {downloadAttachment, saveAttachmentToFiles} from '../service/AttachmentService'
import {getUserCalendars, importCalendarEvent} from '../service/DAVService'

export default {
	name: 'MessageAttachment',
	components: {
		PopoverMenu,
	},
	mixins: [onClickOutside],
	props: {
		id: {
			type: Number,
			required: true,
		},
		fileName: {
			type: String,
			required: true,
		},
		url: {
			type: String,
			required: true,
		},
		size: {
			type: Number,
			required: true,
		},
		mimeUrl: {
			type: String,
			required: true,
		},
		isImage: {
			type: Boolean,
			default: false,
		},
		isCalendarEvent: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			savingToCloud: false,
			loadingCalendars: false,
			calendars: [],
			showCalendarPopover: false,
		}
	},
	computed: {
		label() {
			return this.fileName + ' (' + formatFileSize(this.size) + ')'
		},
		calendarMenuEntries() {
			return this.calendars.map(cal => {
				return {
					icon: 'icon-add',
					text: cal.displayname,
					action: this.importCalendar(cal.url),
				}
			})
		},
	},
	methods: {
		humanReadable(size) {
			return formatFileSize(size)
		},
		saveToCloud() {
			const saveAttachment = (accountId, folderId, messageId, attachmentId) => directory => {
				return saveAttachmentToFiles(accountId, folderId, messageId, attachmentId, directory)
			}
			const {accountId, folderId, id} = parseUid(this.$route.params.messageUid)
			const picker = getFilePickerBuilder(t('mail', 'Choose a folder to store the attachment in'))
				.setMultiSelect(false)
				.addMimeTypeFilter('httpd/unix-directory')
				.setModal(true)
				.setType(1)
				.build()

			return picker
				.pick()
				.then(dest => {
					this.savingToCloud = true
					return dest
				})
				.then(saveAttachment(accountId, folderId, id, this.id))
				.then(() => Logger.info('saved'))
				.catch(e => Logger.error('not saved', {error: e}))
				.then(() => (this.savingToCloud = false))
		},
		download() {
			window.open(this.url)
			window.focus()
		},
		loadCalendars() {
			this.loadingCalendars = true
			getUserCalendars().then(calendars => {
				this.calendars = calendars
				this.showCalendarPopover = true
				this.loadingCalendars = false
			})
		},
		closeCalendarPopover() {
			this.showCalendarPopover = false
		},
		importCalendar(url) {
			return () => {
				downloadAttachment(this.url)
					.then(importCalendarEvent(url))
					.then(() => Logger.info('calendar imported'))
					.catch(e => Logger.error('import error', {error: e}))
					.then(() => (this.showCalendarPopover = false))
			}
		},
	},
}
</script>

<style scoped>
.attachment {
	position: relative;
	display: inline-block;
	border: 1px solid var(--color-border);
	border-radius: 3px;
	margin: 0 10px 10px 0;
	padding: 5px;
}

.attachment:hover,
.attachment span:hover {
	background-color: var(--color-background-dark);
	cursor: pointer;
}

@media only screen and (max-width: 768px) {
	.attachment {
		width: calc(100% - 5px);
	}
}

@media only screen and (min-width: 769px) and (max-width: 1400px) {
	.attachment {
		width: calc(50% - 10px);
	}
}

@media only screen and (min-width: 1401px) {
	.attachment {
		width: calc(33% - 12px);
	}
}

.mail-attached-image {
	display: block;
	max-width: 100%;
	max-height: 120px;
}

.attachment-save-to-cloud,
.attachment-download,
.attachment-import {
	position: absolute;
	height: 32px;
	width: 32px;
	margin: 0;
	bottom: 0;
	background-color: transparent;
	border-color: transparent;
}

.attachment-save-to-cloud {
	right: 0;
}

.attachment-download {
	right: 32px;
	opacity: 0.6;
}

.attachment-import {
	right: 64px;
}

.attachment-import-popover {
	right: 32px;
	top: 42px;
}

.attachment-import-popover::after {
	right: 32px;
}

.attachment-name {
	display: inline-block;
	width: calc(100% - 108px);
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	vertical-align: middle;
}

/* show attachment size less prominent */
.attachment-size {
	-ms-filter: 'progid:DXImageTransform.Microsoft.Alpha(Opacity=50)';
	opacity: 0.5;
}

.attachment-icon {
	vertical-align: middle;
}
</style>
