<template>
	<AppContentDetails>
		<Loading v-if="loading" />
		<Error
			v-else-if="!message"
			:error="error && error.message ? error.message : t('mail', 'Not found')"
			:message="errorMessage"
			:data="error"
		>
		</Error>
		<template v-else>
			<div id="mail-message-header" class="section">
				<h2 :title="message.subject">{{ message.subject }}</h2>
				<p class="transparency">
					<AddressList :entries="message.from" />
					to
					<!-- TODO: translate -->
					<AddressList :entries="message.to" />
					<template v-if="message.cc.length">
						(cc
						<!-- TODO: translate -->
						<AddressList :entries="message.cc" /><!--
						-->)
					</template>
				</p>
			</div>
			<div class="mail-message-body">
				<MessageHTMLBody v-if="message.hasHtmlBody" :url="htmlUrl" @loaded="onHtmlBodyLoaded" />
				<MessagePlainTextBody v-else :body="message.body" :signature="message.signature" />
				<MessageAttachments :attachments="message.attachments" />
				<div id="reply-composer"></div>
				<input id="forward-button" type="button" :value="t('mail', 'Forward')" @click="forwardMessage" />
			</div>
			<Composer
				v-if="!message.hasHtmlBody || htmlBodyLoaded"
				:from-account="message.accountId"
				:to="replyRecipient.to"
				:cc="replyRecipient.cc"
				:subject="replySubject"
				:body="replyBody"
				:reply-to="replyTo"
				:send="sendReply"
				:draft="saveReplyDraft"
			/>
		</template>
	</AppContentDetails>
</template>

<script>
import AppContentDetails from 'nextcloud-vue/dist/Components/AppContentDetails'
import {generateUrl} from 'nextcloud-router'

import AddressList from './AddressList'
import {buildReplyBody, buildRecipients as buildReplyRecipients, buildReplySubject} from '../ReplyBuilder'
import Composer from './Composer'
import Error from './Error'
import {getRandomMessageErrorMessage} from '../util/ErrorMessageFactory'
import {htmlToText} from '../util/HtmlHelper'
import MessageHTMLBody from './MessageHTMLBody'
import MessagePlainTextBody from './MessagePlainTextBody'
import Loading from './Loading'
import Logger from '../logger'
import MessageAttachments from './MessageAttachments'
import {saveDraft, sendMessage} from '../service/MessageService'

export default {
	name: 'Message',
	components: {
		AddressList,
		AppContentDetails,
		Composer,
		Error,
		Loading,
		MessageAttachments,
		MessageHTMLBody,
		MessagePlainTextBody,
	},
	data() {
		return {
			loading: true,
			message: undefined,
			errorMessage: '',
			error: undefined,
			htmlBodyLoaded: false,
			replyRecipient: {},
			replySubject: '',
			replyBody: '',
		}
	},
	computed: {
		htmlUrl() {
			return generateUrl('/apps/mail/api/accounts/{accountId}/folders/{folderId}/messages/{id}/html', {
				accountId: this.message.accountId,
				folderId: this.message.folderId,
				id: this.message.id,
			})
		},
		replyTo() {
			return {
				accountId: this.message.accountId,
				folderId: this.message.folderId,
				messageId: this.message.id,
			}
		},
	},
	watch: {
		$route(to, from) {
			this.fetchMessage()
		},
	},
	created() {
		this.fetchMessage()
	},
	methods: {
		fetchMessage() {
			this.loading = true
			this.message = undefined
			this.errorMessage = ''
			this.error = undefined
			this.replyRecipient = {}
			this.replySubject = ''
			this.replyBody = ''
			this.htmlBodyLoaded = false

			const messageUid = this.$route.params.messageUid

			this.$store
				.dispatch('fetchMessage', messageUid)
				.then(message => {
					// TODO: add timeout so that message isn't flagged when only viewed
					// for a few seconds
					if (message.uid !== this.$route.params.messageUid) {
						Logger.debug("User navigated away, loaded message won't be shown nor flagged as seen")
						return
					}

					this.message = message

					if (this.message === undefined) {
						Logger.info('message could not be found', {messageUid})
						this.errorMessage = getRandomMessageErrorMessage()
						this.loading = false
						return
					}

					const account = this.$store.getters.getAccount(message.accountId)
					this.replyRecipient = buildReplyRecipients(message, {
						label: account.name,
						email: account.emailAddress,
					})
					this.replySubject = buildReplySubject(message.subject)

					if (!message.hasHtmlBody) {
						this.setReplyText(message.body)
					}

					this.loading = false

					const envelope = this.$store.getters.getEnvelope(message.accountId, message.folderId, message.id)
					if (!envelope.flags.unseen) {
						// Already seen -> no change necessary
						return
					}

					return this.$store.dispatch('toggleEnvelopeSeen', envelope)
				})
				.catch(error => {
					Logger.error('could not load message ', {messageUid, error})
					if (error.isError) {
						this.errorMessage = t('mail', 'Could not load your message')
						this.error = error
						this.loading = false
					}
				})
		},
		setReplyText(text) {
			const bodyText = htmlToText(text)

			this.$store.commit('setMessageBodyText', {
				uid: this.message.uid,
				bodyText,
			})

			this.replyBody = buildReplyBody(this.message.bodyText, this.message.from[0], this.message.dateInt)
		},
		onHtmlBodyLoaded(bodyString) {
			this.setReplyText(bodyString)
			this.htmlBodyLoaded = true
		},
		saveReplyDraft(data) {
			return saveDraft(data.account, data).then(({uid}) => uid)
		},
		sendReply(data) {
			return sendMessage(data.account, data)
		},
		forwardMessage() {
			this.$router.push({
				name: 'message',
				params: {
					accountId: this.$route.params.accountId,
					folderId: this.$route.params.folderId,
					messageUid: 'new',
				},
				query: {
					uid: this.message.uid,
				},
			})
		},
	},
}
</script>

<style>
.mail-message-body {
	margin-bottom: 100px;
}

#mail-message-header h2,
#mail-message-header p {
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	padding-bottom: 7px;
	margin-bottom: 0;
}

#mail-content,
.mail-message-attachments {
	margin: 10px 10px 50px 30px;
}

.mail-message-attachments {
	margin-top: 10px;
}

#mail-content iframe {
	width: 100%;
}

#show-images-text {
	display: none;
}

#mail-content a,
.mail-signature a {
	color: #07d;
	border-bottom: 1px dotted #07d;
	text-decoration: none;
	word-wrap: break-word;
}

#mail-message-header .transparency {
	opacity: 0.6;
}

#mail-message-header .transparency a {
	font-weight: bold;
}

@media print {
	#mail-message-header {
		position: relative;
	}

	#header,
	#app-navigation,
	#reply-composer,
	#forward-button,
	#mail-message-has-blocked-content,
	.app-content-list,
	.message-composer,
	.mail-message-attachments {
		display: none !important;
	}
	#app-content {
		margin-left: 0 !important;
	}
	.mail-message-body {
		margin-bottom: 0 !important;
	}
}
</style>
