<template>
	<router-link class="app-content-list-item" :class="{unseen: data.flags.unseen, draft}" :to="link">
		<div
			v-if="showAccountColor"
			class="mail-message-account-color"
			:style="{'background-color': accountColor}"
		></div>
		<div
			class="app-content-list-item-star icon-starred"
			:data-starred="data.flags.flagged ? 'true' : 'false'"
			@click.prevent="onToggleFlagged"
		></div>
		<div class="app-content-list-item-icon">
			<Avatar :display-name="sender" :email="senderEmail" />
		</div>
		<div class="app-content-list-item-line-one" :title="sender">
			{{ sender }}
		</div>
		<div class="app-content-list-item-line-two" :title="data.subject">
			<span v-if="data.flags.answered" class="icon-reply" />
			<span v-if="data.flags.hasAttachments" class="icon-public icon-attachment" />
			<span v-if="draft" class="draft">
				<em>{{ t('mail', 'Draft: ') }}</em>
			</span>
			{{ data.subject }}
		</div>
		<div class="app-content-list-item-details date">
			<Moment :timestamp="data.dateInt" />
		</div>
		<Actions class="app-content-list-item-menu" menu-align="right">
			<ActionButton icon="icon-mail" @click="onToggleSeen">{{
				data.flags.unseen ? t('mail', 'Mark read') : t('mail', 'Mark unread')
			}}</ActionButton>
			<ActionButton icon="icon-delete" @click="onDelete">{{ t('mail', 'Delete') }}</ActionButton>
		</Actions>
	</router-link>
</template>

<script>
import Actions from 'nextcloud-vue/dist/Components/Actions'
import ActionButton from 'nextcloud-vue/dist/Components/ActionButton'
import Moment from './Moment'

import Avatar from './Avatar'
import {calculateAccountColor} from '../util/AccountColor'

export default {
	name: 'Envelope',
	components: {
		Actions,
		ActionButton,
		Avatar,
		Moment,
	},
	props: {
		data: {
			type: Object,
			required: true,
		},
		showAccountColor: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		accountColor() {
			return calculateAccountColor(this.$store.getters.getAccount(this.data.accountId).emailAddress)
		},
		draft() {
			return this.data.flags.draft
		},
		link() {
			if (this.draft) {
				// TODO: does not work with a unified drafts folder
				//       the query should also contain the account and folder
				//       id for that to work
				return {
					name: 'message',
					params: {
						accountId: this.$route.params.accountId,
						folderId: this.$route.params.folderId,
						messageUid: 'new',
						draftUid: this.data.uid,
					},
					exact: true,
				}
			} else {
				return {
					name: 'message',
					params: {
						accountId: this.$route.params.accountId,
						folderId: this.$route.params.folderId,
						messageUid: this.data.uid,
					},
					exact: true,
				}
			}
		},
		sender() {
			if (this.data.from.length === 0) {
				// No sender
				return '?'
			}

			const first = this.data.from[0]
			return first.label || first.email
		},
		senderEmail() {
			if (this.data.from.length > 0) {
				return this.data.from[0].email
			} else {
				return undefined
			}
		},
	},
	methods: {
		onToggleFlagged() {
			this.$store.dispatch('toggleEnvelopeFlagged', this.data)
		},
		onToggleSeen() {
			this.$store.dispatch('toggleEnvelopeSeen', this.data)
		},
		onDelete(e) {
			// Don't navigate to the deleted message
			e.preventDefault()

			this.$emit('delete', this.data)
			this.$store.dispatch('deleteMessage', this.data)
		},
	},
}
</script>

<style scoped>
.mail-message-account-color {
	position: absolute;
	left: 0px;
	width: 2px;
	height: 69px;
	z-index: 1;
}

.app-content-list-item.unseen {
	font-weight: bold;
}
.app-content-list-item.draft .app-content-list-item-line-two {
	font-style: italic;
}

.icon-reply,
.icon-attachment {
	display: inline-block;
	vertical-align: text-top;
}

.icon-reply {
	background-image: url('../../img/reply.svg');
	-ms-filter: 'progid:DXImageTransform.Microsoft.Alpha(Opacity=50)';
	opacity: 0.5;
}

.icon-attachment {
	-ms-filter: 'progid:DXImageTransform.Microsoft.Alpha(Opacity=25)';
	opacity: 0.25;
}
</style>
