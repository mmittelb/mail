<template>
	<AppContentDetails>
		<Loading v-if="loading" />
		<Error
			v-else-if="error"
			:error="error && error.message ? error.message : t('mail', 'Not found')"
			:message="errorMessage"
			:data="error"
		>
		</Error>
		<Composer
			v-else
			:from-account="composerData.accountId"
			:to="composerData.to"
			:cc="composerData.cc"
			:bcc="composerData.bcc"
			:subject="composerData.subject"
			:body="composerData.body"
			:draft="saveDraft"
			:send="sendMessage"
		/>
	</AppContentDetails>
</template>

<script>
import AppContentDetails from 'nextcloud-vue/dist/Components/AppContentDetails'

import {buildFowardSubject, buildReplyBody} from '../ReplyBuilder'
import Composer from './Composer'
import {getRandomMessageErrorMessage} from '../util/ErrorMessageFactory'
import Error from './Error'
import Loading from './Loading'
import Logger from '../logger'
import {saveDraft, sendMessage} from '../service/MessageService'

export default {
	name: 'NewMessageDetail',
	components: {
		AppContentDetails,
		Composer,
		Error,
		Loading,
	},
	data() {
		return {
			loading: false,
			draft: undefined,
			errorMessage: '',
			error: undefined,
		}
	},
	computed: {
		composerData() {
			if (this.draft !== undefined) {
				Logger.info('todo: handle draft data')
				return {
					to: this.draft.to,
					cc: this.draft.cc,
					bcc: this.draft.bcc, // TODO: impl in composer
					subject: this.draft.subject,
					body: this.draft.body,
				}
			} else if (this.$route.query.uid !== undefined) {
				// Forwarded message

				const message = this.$store.getters.getMessageByUid(this.$route.query.uid)
				Logger.debug('forwaring message', message)

				return {
					to: [],
					cc: [],
					subject: buildFowardSubject(message.subject),
					body: buildReplyBody(message.bodyText, message.from[0], message.dateInt),
				}
			} else {
				// New or mailto: message

				let accountId
				// Only preselect an account when we're not in a unified mailbox
				if (this.$route.params.accountId !== 0 && this.$route.params.accountId !== '0') {
					accountId = parseInt(this.$route.params.accountId, 10)
				}

				return {
					accountId,
					to: this.stringToRecipients(this.$route.query.to),
					cc: this.stringToRecipients(this.$route.query.cc),
					subject: this.$route.query.subject || '',
					body: this.$route.query.body || '',
				}
			}
		},
	},
	watch: {
		$route(to, from) {
			// `saveDraft` replaced the current URL with the updated draft UID
			// in that case we don't really start a new draft but just keep the
			// URL consistent, hence not loading anything
			if (this.draft && to.name === 'message' && to.params.draftUid === this.draft.uid) {
				Logger.debug('detected navigation to current (new) draft UID, not reloading')
				return
			}

			this.fetchMessage()
		},
	},
	created() {
		this.fetchMessage()
	},
	methods: {
		stringToRecipients(str) {
			if (str === undefined) {
				return []
			}

			return [
				{
					label: str,
					email: str,
				},
			]
		},
		fetchMessage() {
			this.draft = undefined
			this.errorMessage = ''
			this.error = undefined

			const draftUid = this.$route.params.draftUid
			if (draftUid === undefined) {
				Logger.debug('not a draft, nothing to fetch')
				// Nothing to fetch
				return
			}

			this.loading = true

			this.$store
				.dispatch('fetchMessage', draftUid)
				.then(draft => {
					if (draft.uid !== this.$route.params.draftUid) {
						Logger.debug("User navigated away, loaded draft won't be shown")
						return
					}

					this.draft = draft

					if (this.draft === undefined) {
						Logger.info('draft could not be found', {draftUid})
						this.errorMessage = getRandomMessageErrorMessage()
						this.loading = false
						return
					}

					this.loading = false
				})
				.catch(error => {
					Logger.error('could not load draft ' + draftUid, {error})
					if (error.isError) {
						this.errorMessage = t('mail', 'Could not load your draft')
						this.error = error
						this.loading = false
					}
				})
		},
		saveDraft(data) {
			if (data.draftUID === undefined && this.draft) {
				Logger.debug('draft data does not have a draftUID, adding one')
				data.draftUID = this.draft.id
			}
			return saveDraft(data.account, data).then(({uid}) => {
				if (this.draft === undefined) {
					return uid
				}

				Logger.info('replacing draft ' + this.draft.uid + ' with ' + uid)
				const update = {
					draft: this.draft,
					uid,
					data,
				}
				return this.$store
					.dispatch('replaceDraft', update)
					.then(() =>
						this.$router.replace({
							name: 'message',
							params: {
								accountId: this.$route.params.accountId,
								folderId: this.$route.params.folderId,
								messageUid: 'new',
								draftUid: this.draft.uid,
							},
						})
					)
					.then(() => uid)
			})
		},
		sendMessage(data) {
			return sendMessage(data.account, data)
		},
	},
}
</script>
