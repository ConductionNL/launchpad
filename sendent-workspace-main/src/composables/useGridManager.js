import { ref, nextTick } from 'vue'
import { GridStack } from 'gridstack'

const WIDGET_TYPE_MAP = {
	text: 'TextDisplayWidget',
	image: 'ImageWidget',
	link: 'LinkButtonWidget',
	label: 'LabelWidget',
	widget: 'ApiWidget',
}

export function useGridManager({ layout, gridContainer, isAdmin = false, onLayoutChanged = null }) {
	const grid = ref(null)
	const showModal = ref(false)
	const showAddDropdown = ref(false)
	const preselectedType = ref(null)
	const showContextMenu = ref(false)
	const contextMenuX = ref(0)
	const contextMenuY = ref(0)
	const selectedWidget = ref(null)
	const editingWidgetData = ref(null)

	const notifyLayoutChanged = () => {
		if (onLayoutChanged) {
			onLayoutChanged(layout.value)
		}
	}

	const initGrid = (options = {}) => {
		if (!gridContainer.value || grid.value) return

		grid.value = GridStack.init({
			column: 12,
			cellHeight: 60,
			margin: 8,
			float: true,
			animate: true,
			staticGrid: !isAdmin,
			acceptWidgets: isAdmin,
			removable: false,
			columnOpts: {
				breakpoints: [
					{ w: 1400, c: 12 },
					{ w: 1100, c: 8 },
					{ w: 768, c: 4 },
					{ w: 480, c: 1 },
				],
				layout: 'moveScale',
			},
			...options,
		}, gridContainer.value)

		if (isAdmin) {
			grid.value.on('change', (event, items) => {
				if (!items) return
				items.forEach(item => {
					const widget = layout.value.find(w => w.id === item.el.dataset.id)
					if (widget) {
						widget.x = item.x
						widget.y = item.y
						widget.w = item.w
						widget.h = item.h
					}
				})
				notifyLayoutChanged()
			})
		}
	}

	const destroyGrid = () => {
		if (grid.value) {
			grid.value.removeAll(false)
			grid.value.destroy(false)
			grid.value = null
		}
	}

	const getWidgetComponent = (widget) => {
		return WIDGET_TYPE_MAP[widget.type] || 'TextDisplayWidget'
	}

	const getWidgetProps = (widget) => {
		const baseProps = {
			widget,
			...widget.content,
		}

		return baseProps
	}

	const openAddWidgetModal = (type) => {
		preselectedType.value = type
		showModal.value = true
		showAddDropdown.value = false
	}

	const closeModal = () => {
		showModal.value = false
		preselectedType.value = null
		editingWidgetData.value = null
	}

	const moveCollidingWidgets = (newW, newH) => {
		if (!grid.value) return

		layout.value.forEach(widget => {
			const wx = parseInt(widget.x) || 0
			const wy = parseInt(widget.y) || 0
			const ww = parseInt(widget.w) || 1
			const wh = parseInt(widget.h) || 1

			const overlaps = wx < newW && (wx + ww) > 0 && wy < newH && (wy + wh) > 0
			if (!overlaps) return

			const newY = newH
			widget.y = newY

			const el = gridContainer.value.querySelector(`[data-id="${widget.id}"]`)
			if (el) {
				grid.value.update(el, { y: newY })
			}
		})
	}

	const handleWidgetSubmit = async (widgetData) => {
		const newW = widgetData.w || 2
		const newH = widgetData.h || 2

		if (editingWidgetData.value) {
			const existing = layout.value.find(w => w.id === editingWidgetData.value.id)
			if (existing) {
				existing.type = widgetData.type
				existing.w = newW
				existing.h = newH
				existing.content = widgetData.content || {}

				const el = gridContainer.value?.querySelector(`[data-id="${existing.id}"]`)
				if (el && grid.value) {
					grid.value.update(el, { w: newW, h: newH })
				}
			}
			notifyLayoutChanged()
			closeModal()
			return
		}

		moveCollidingWidgets(newW, newH)

		const newWidget = {
			id: 'widget_' + Date.now(),
			type: widgetData.type,
			x: 0,
			y: 0,
			w: newW,
			h: newH,
			content: widgetData.content || {},
		}

		layout.value.push(newWidget)

		if (grid.value) {
			// Wait for Vue to render the new DOM element, then register it with GridStack
			await nextTick()
			const el = gridContainer.value?.querySelector(`[data-id="${newWidget.id}"]`)
			if (el) {
				grid.value.makeWidget(el)
			}
		}

		notifyLayoutChanged()
		closeModal()
	}

	const onWidgetRightClick = (event, widget) => {
		if (!isAdmin) return

		event.preventDefault()
		selectedWidget.value = widget
		contextMenuX.value = event.clientX
		contextMenuY.value = event.clientY
		showContextMenu.value = true
	}

	const closeContextMenu = () => {
		showContextMenu.value = false
		selectedWidget.value = null
	}

	const editWidget = (widget) => {
		editingWidgetData.value = widget
		preselectedType.value = widget.type
		showModal.value = true
		closeContextMenu()
	}

	const removeWidget = (widget) => {
		const index = layout.value.findIndex(w => w.id === widget.id)
		if (index > -1) {
			layout.value.splice(index, 1)
			if (grid.value) {
				const el = gridContainer.value.querySelector(`[data-id="${widget.id}"]`)
				if (el) {
					grid.value.removeWidget(el)
				}
			}
		}
		notifyLayoutChanged()
		closeContextMenu()
	}

	const handleClickOutside = (event) => {
		const dropdown = document.querySelector('.add-widget-dropdown')
		if (dropdown && !dropdown.contains(event.target)) {
			showAddDropdown.value = false
		}

		if (showContextMenu.value && !event.target.closest('.context-menu')) {
			closeContextMenu()
		}
	}

	return {
		grid,
		showModal,
		showAddDropdown,
		preselectedType,
		showContextMenu,
		contextMenuX,
		contextMenuY,
		selectedWidget,
		editingWidgetData,
		initGrid,
		destroyGrid,
		getWidgetComponent,
		getWidgetProps,
		openAddWidgetModal,
		closeModal,
		handleWidgetSubmit,
		onWidgetRightClick,
		closeContextMenu,
		editWidget,
		removeWidget,
		handleClickOutside,
	}
}
