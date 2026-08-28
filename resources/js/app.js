import Alpine from 'alpinejs'
import hud from './hud'
import popover from './popover'
import settings from './settings'

Alpine.data('hud', hud)
Alpine.data('popover', popover)
Alpine.data('settings', settings)

window.Alpine = Alpine
Alpine.start()
