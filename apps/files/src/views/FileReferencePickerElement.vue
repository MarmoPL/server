<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div :id="containerId">
		<FilePicker v-bind="filepickerOptions" @close="onClose" />
	</div>
</template>

<script lang="ts">
import type { IFilePickerButton } from '@nextcloud/dialogs'
import type { Node as NcNode } from '@nextcloud/files'

import { FilePickerVue as FilePicker } from '@nextcloud/dialogs/filepicker.js'
import { translate as t } from '@nextcloud/l10n'
import { defineComponent } from 'vue'
import { generateFileUrl } from '../../../files_sharing/src/utils/generateUrl.ts'

export default defineComponent({
	name: 'FileReferencePickerElement',
	components: {
		FilePicker,
	},

	props: {
		providerId: {
			type: String,
			required: true,
		},

		accessible: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		containerId() {
			return `filepicker-${Math.random().toString(36).slice(7)}`
		},

		filepickerOptions() {
			return {
				allowPickDirectory: true,
				buttons: this.buttonFactory,
				container: `#${this.containerId}`,
				multiselect: false,
				name: t('files', 'Select file or folder to link to'),
			}
		},
	},

	methods: {
		t,

		buttonFactory(selected: NcNode[]): IFilePickerButton[] {
			const buttons = [] as IFilePickerButton[]
			const [node] = selected
			// Do not allow selecting the users root folder or if no node is selected
			if (node === undefined || node.path === '/') {
				return []
			}
			buttons.push({
				label: t('files', 'Choose {file}', { file: node.displayname }),
				type: 'primary',
				callback: this.onClose,
			})
			return buttons
		},

		onClose(nodes?: NcNode[]) {
			if (nodes === undefined || nodes.length === 0) {
				this.$emit('cancel')
			} else {
				this.onSubmit(nodes[0])
			}
		},

		onSubmit(node: NcNode) {
			this.$emit('submit', generateFileUrl(node.fileid!))
		},
	},
})
</script>
