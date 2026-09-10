<template>
  <div class="component-map">
    <div class="map-canvas" ref="mapElement"></div>

    <div class="map-tools">
      <button
        v-if="enableMapTypeToggle"
        class="btn-map btn-map-type"
        :class="{ 'is-satellite': currentMapType === 'satellite' }"
        type="button"
        :title="currentMapType === 'satellite' ? 'Alternar para mapa' : 'Alternar para satélite'"
        @click="toggleMapType"
      >
        <i
          class="ki-filled"
          :class="currentMapType === 'satellite' ? 'ki-map' : 'ki-satellite'"
          aria-hidden="true"
        ></i>
        <span>{{ currentMapType === 'satellite' ? 'Mapa' : 'Satélite' }}</span>
      </button>

      <button
        v-if="enablePolygon"
        class="btn-map btn-draw-polygon"
        :class="{ 'is-active': drawingMode === 'polygon' }"
        type="button"
        :title="polygonButtonTitle"
        :aria-pressed="drawingMode === 'polygon'"
        :disabled="manualDrawingMode === 'polygon' && manualVertexCount < 3"
        @click="setDrawingMode('polygon')"
      >
        <i
          class="ki-filled"
          :class="drawingMode === 'polygon' ? 'ki-check' : 'ki-pencil'"
          aria-hidden="true"
        ></i>
        <span>{{ drawingMode === 'polygon' ? 'Concluir' : 'Desenhar área' }}</span>
      </button>

      <button
        v-if="enablePolyline"
        class="btn-map btn-draw-polyline"
        type="button"
        title="Desenhar linha"
        @click="setDrawingMode('polyline')"
      >
        <i class="ki-filled ki-route" aria-hidden="true"></i>
        <span>Desenhar linha</span>
      </button>

      <button
        v-if="enableRectangle"
        class="btn-map btn-draw-rectangle"
        type="button"
        title="Desenhar retângulo"
        @click="setDrawingMode('rectangle')"
      >
        <i class="ki-filled ki-element-11" aria-hidden="true"></i>
        <span>Retângulo</span>
      </button>

      <button
        v-if="enableCircle"
        class="btn-map btn-draw-circle"
        type="button"
        title="Desenhar círculo"
        @click="setDrawingMode('circle')"
      >
        <i class="ki-filled ki-abstract-8" aria-hidden="true"></i>
        <span>Círculo</span>
      </button>

      <button
        v-if="enableMarker"
        class="btn-map btn-draw-marker"
        type="button"
        title="Adicionar marcador"
        @click="setDrawingMode('marker')"
      >
        <i class="ki-filled ki-geolocation" aria-hidden="true"></i>
        <span>Marcador</span>
      </button>

      <button
        v-if="enableShapes && hasErasableShape"
        class="btn-map btn-erase-all"
        type="button"
        title="Apagar desenho"
        @click="handleEraseAll"
      >
        <i class="ki-filled ki-trash" aria-hidden="true"></i>
        <span>Apagar</span>
      </button>

      <button
        v-if="enableCancelDrawing && isDrawing && hasShape"
        class="btn-map btn-cancel-draw"
        type="button"
        title="Cancelar desenho em andamento"
        @click="cancelDrawing"
      >
        <i class="ki-filled ki-cross-circle" aria-hidden="true"></i>
        <span>Cancelar</span>
      </button>
    </div>

    <div v-if="manualDrawingMode === 'polygon'" class="map-drawing-hint">
      <strong>Desenhando polígono</strong>
      <span>Clique no mapa para adicionar os vértices (mínimo de 3).</span>
      <span v-if="manualVertexCount < 3">Adicionados: {{ manualVertexCount }} de 3.</span>
      <span v-else>Área pronta: clique em “Concluir”.</span>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue'

const props = defineProps({
  enableMapTypeToggle: { type: Boolean, default: true },
  initialMapType: { type: String, default: 'roadmap' },
  enablePolygon: { type: Boolean, default: false },
  enablePolyline: { type: Boolean, default: false },
  enableRectangle: { type: Boolean, default: false },
  enableCircle: { type: Boolean, default: false },
  enableMarker: { type: Boolean, default: false },
  enableShapes: { type: Boolean, default: false },
  enableCancelDrawing: { type: Boolean, default: false },
  defaultZoom: { type: Number, default: 13 },
  defaultCenter: {
    type: Object,
    default: () => ({ lat: -16.3285, lng: -48.9534 })
  },
  modelValue: {
    type: Array,
    default: () => []
  },
  singleShape: { type: Boolean, default: false }
})

const emit = defineEmits(['update:modelValue', 'polygon-change', 'map-ready'])

const GOOGLE_MAPS_KEY = 'AIzaSyDVD82P95dwXstBpOvla2MliAa6J-OblPs'

let googleMapsPromise = null

const mapElement = ref(null)
const map = shallowRef(null)
const drawingManager = shallowRef(null)
const drawingMode = ref(null)
const manualDrawingMode = ref(null)
const manualDrawingShape = shallowRef(null)
const manualMapClickListener = shallowRef(null)
const manualVertexCount = ref(0)
const shapes = shallowRef([])
const vertexMarkers = shallowRef([])
const activeShape = shallowRef(null)
const activeShapeType = ref(null)
const ready = ref(false)
const syncingFromModel = ref(false)
const currentMapType = ref('roadmap')

const CIRCLE_APPROXIMATION_SEGMENTS = 60
const COORDINATE_EPSILON = 0.0000001
const VERTEX_MARKER_COLOR = '#0d6efd'

const hasShape = computed(() => Boolean(
  manualDrawingShape.value ||
  activeShape.value ||
  shapes.value.length
))
const isDrawing = computed(() => Boolean(drawingMode.value || manualDrawingMode.value))
const hasErasableShape = computed(() => {
  if (manualDrawingMode.value === 'polygon') {
    return manualVertexCount.value > 0
  }

  return hasShape.value
})
const polygonButtonTitle = computed(() => {
  if (manualDrawingMode.value !== 'polygon') return 'Desenhar polígono'
  if (manualVertexCount.value < 3) {
    return `Adicione mais ${3 - manualVertexCount.value} vértice(s) para concluir`
  }

  return 'Concluir polígono'
})

function hasDrawingToolsEnabled() {
  return Boolean(
    props.enablePolygon ||
    props.enablePolyline ||
    props.enableRectangle ||
    props.enableCircle ||
    props.enableMarker
  )
}

function needsGeometryLibrary() {
  return Boolean(props.enableCircle)
}

function hasRequiredGoogleMaps() {
  const maps = window.google?.maps
  if (!maps?.Map || !maps?.Polygon || !maps?.LatLngBounds) return false
  if (needsGeometryLibrary() && !maps?.geometry?.spherical) return false

  return true
}

async function importMissingGoogleMapsLibraries() {
  const maps = window.google?.maps
  if (typeof maps?.importLibrary !== 'function') return

  await maps.importLibrary('maps')

  const imports = []
  if (needsGeometryLibrary() && !maps?.geometry?.spherical) {
    imports.push(maps.importLibrary('geometry'))
  }

  if (imports.length) {
    await Promise.all(imports)
  }
}

function normalizeMapType(value) {
  const parsed = String(value || '').toLowerCase()
  if (parsed === 'satellite') return 'satellite'
  return 'roadmap'
}

function toggleMapType() {
  if (!map.value) return

  const nextType = currentMapType.value === 'satellite' ? 'roadmap' : 'satellite'
  map.value.setMapTypeId(nextType)
}

function normalizePoints(points) {
  if (!Array.isArray(points)) return []

  const parsed = points
    .map(point => ({
      lat: Number(point?.lat),
      lng: Number(point?.lng)
    }))
    .filter(point => Number.isFinite(point.lat) && Number.isFinite(point.lng))

  if (parsed.length < 2) return parsed

  const first = parsed[0]
  const last = parsed[parsed.length - 1]
  if (first.lat === last.lat && first.lng === last.lng) {
    parsed.pop()
  }

  return parsed
}

function pointsAreEqual(a, b) {
  if (!Array.isArray(a) || !Array.isArray(b)) return false
  if (a.length !== b.length) return false

  for (let i = 0; i < a.length; i += 1) {
    const latDelta = Math.abs(Number(a[i]?.lat) - Number(b[i]?.lat))
    const lngDelta = Math.abs(Number(a[i]?.lng) - Number(b[i]?.lng))
    if (latDelta > COORDINATE_EPSILON || lngDelta > COORDINATE_EPSILON) {
      return false
    }
  }

  return true
}

function getPolygonPath(polygon, { create = false } = {}) {
  if (typeof polygon?.getPath !== 'function') return null

  let path = polygon.getPath()
  if (!path && create && typeof polygon.setPath === 'function') {
    polygon.setPath([])
    path = polygon.getPath()
  }

  if (!path || typeof path.getArray !== 'function') return null
  return path
}

function getPolygonPoints(polygon) {
  const path = getPolygonPath(polygon)
  if (!path) return []

  const coords = path.getArray()
    .filter(point => typeof point?.lat === 'function' && typeof point?.lng === 'function')
    .map(point => ({
      lat: point.lat(),
      lng: point.lng()
    }))

  return normalizePoints(coords)
}

function getCirclePoints(circle) {
  if (!circle || !circle.getCenter || !circle.getRadius) return []
  if (!window.google?.maps?.geometry?.spherical?.computeOffset) return []

  const center = circle.getCenter()
  const radius = Number(circle.getRadius())
  if (!center || !Number.isFinite(radius) || radius <= 0) return []

  const points = []
  for (let i = 0; i < CIRCLE_APPROXIMATION_SEGMENTS; i += 1) {
    const heading = (i / CIRCLE_APPROXIMATION_SEGMENTS) * 360
    const point = window.google.maps.geometry.spherical.computeOffset(center, radius, heading)
    points.push({
      lat: point.lat(),
      lng: point.lng()
    })
  }

  return normalizePoints(points)
}

function getCurrentShapePoints() {
  if (!activeShape.value) return []
  if (activeShapeType.value === 'circle') {
    return getCirclePoints(activeShape.value)
  }

  return getPolygonPoints(activeShape.value)
}

function emitShapeGeometry() {
  if (!activeShape.value) {
    emit('update:modelValue', [])
    emit('polygon-change', [])
    return
  }

  const points = getCurrentShapePoints()
  emit('update:modelValue', points)
  emit('polygon-change', points)
}

function clearVertexMarkers() {
  vertexMarkers.value.forEach(marker => marker?.setMap?.(null))
  vertexMarkers.value = []
}

function createVertexMarker(point, index) {
  if (!map.value || !window.google?.maps?.Marker || !window.google?.maps?.SymbolPath) return null

  return new window.google.maps.Marker({
    position: point,
    map: map.value,
    clickable: false,
    draggable: false,
    title: `Vértice ${index + 1}`,
    icon: {
      path: window.google.maps.SymbolPath.CIRCLE,
      scale: 5,
      fillColor: VERTEX_MARKER_COLOR,
      fillOpacity: 1,
      strokeColor: '#ffffff',
      strokeWeight: 2
    },
    zIndex: 500 + index
  })
}

function updateVertexMarkers(points = getCurrentShapePoints()) {
  clearVertexMarkers()

  const cleanPoints = normalizePoints(points)
  if (!map.value || cleanPoints.length === 0) return

  vertexMarkers.value = cleanPoints
    .map((point, index) => createVertexMarker(point, index))
    .filter(Boolean)
}

function bindPolygonListeners(polygon) {
  const path = getPolygonPath(polygon, { create: true })
  if (!path || typeof path.addListener !== 'function') return

  const onPathChanged = () => {
    const points = getPolygonPoints(polygon)
    if (manualDrawingShape.value === polygon) {
      manualVertexCount.value = points.length
    }

    updateVertexMarkers(points)
    emitShapeGeometry()
  }

  path.addListener('insert_at', onPathChanged)
  path.addListener('remove_at', onPathChanged)
  path.addListener('set_at', onPathChanged)
  polygon?.addListener?.('dragend', onPathChanged)
}

function bindCircleListeners(circle) {
  if (!circle || typeof circle.addListener !== 'function') return

  const onCircleChanged = () => emitShapeGeometry()
  circle.addListener('center_changed', onCircleChanged)
  circle.addListener('radius_changed', onCircleChanged)
  circle.addListener('dragend', onCircleChanged)
}

function setActiveShape(shape, type = null) {
  activeShape.value = shape || null
  activeShapeType.value = shape ? type : null

  if (!shape) {
    clearVertexMarkers()
    return
  }

  if (type === 'polygon') {
    bindPolygonListeners(shape)
    updateVertexMarkers(getPolygonPoints(shape))
    return
  }

  if (type === 'circle') {
    clearVertexMarkers()
    bindCircleListeners(shape)
  }
}

function removeManualMapClickListener() {
  if (manualMapClickListener.value?.remove) {
    manualMapClickListener.value.remove()
  } else if (manualMapClickListener.value && window.google?.maps?.event?.removeListener) {
    window.google.maps.event.removeListener(manualMapClickListener.value)
  }

  manualMapClickListener.value = null
}

function resetMapDrawingCursor() {
  map.value?.setOptions?.({ draggableCursor: null })
}

function clearGoogleMapsListeners(instance) {
  if (!instance || !window.google?.maps?.event?.clearInstanceListeners) return

  try {
    window.google.maps.event.clearInstanceListeners(instance)
  } catch (_) {
    // O SDK pode já ter descartado internamente a instância.
  }
}

function registerShape(shape) {
  if (!shape || shapes.value.includes(shape)) return
  shapes.value = [...shapes.value, shape]
}

function unregisterShape(shape) {
  if (!shape) return
  shapes.value = shapes.value.filter(item => item !== shape)
}

function detachShape(shape) {
  if (!shape) return

  clearGoogleMapsListeners(getPolygonPath(shape))
  clearGoogleMapsListeners(shape)
  shape?.setMap?.(null)
}

function discardManualShape(shape, { emitModel = true } = {}) {
  if (!shape) return

  detachShape(shape)
  unregisterShape(shape)

  if (activeShape.value === shape) {
    setActiveShape(null)
  }

  if (emitModel) {
    emit('update:modelValue', [])
    emit('polygon-change', [])
  }
}

function stopManualPolygonDrawing({ discard = false, emitModel = true } = {}) {
  const shape = manualDrawingShape.value
  const points = getPolygonPoints(shape)

  removeManualMapClickListener()
  resetMapDrawingCursor()
  manualDrawingMode.value = null
  manualDrawingShape.value = null
  manualVertexCount.value = 0
  drawingMode.value = null

  if (!shape) return

  if (discard) {
    discardManualShape(shape, { emitModel })
    return
  }

  shape.setOptions({
    clickable: true,
    draggable: points.length >= 3,
    editable: points.length >= 3
  })
  updateVertexMarkers(points)

  if (emitModel) {
    emitShapeGeometry()
  }
}

function startManualPolygonDrawing() {
  if (!map.value || !window.google?.maps?.Polygon) return

  if (props.singleShape && hasShape.value) {
    clearAllShapes()
  } else if (!props.singleShape && isDrawing.value) {
    cancelDrawing()
  }

  const polygon = new window.google.maps.Polygon({
    paths: [[]],
    clickable: false,
    editable: false,
    draggable: false,
    fillColor: '#0d6efd',
    strokeColor: '#0d6efd',
    fillOpacity: 0.25,
    strokeWeight: 3,
    zIndex: 20
  })

  polygon.setMap(map.value)
  getPolygonPath(polygon, { create: true })
  registerShape(polygon)
  setActiveShape(polygon, 'polygon')

  drawingMode.value = 'polygon'
  manualDrawingMode.value = 'polygon'
  manualDrawingShape.value = polygon
  manualVertexCount.value = 0
  map.value.setOptions({ draggableCursor: 'crosshair' })

  manualMapClickListener.value = map.value.addListener('click', event => {
    if (!event?.latLng || manualDrawingMode.value !== 'polygon') return
    getPolygonPath(polygon, { create: true })?.push?.(event.latLng)
  })
}

function finishManualPolygonDrawing() {
  if (manualDrawingMode.value !== 'polygon') return false

  const points = getPolygonPoints(manualDrawingShape.value)
  if (points.length < 3) return false

  stopManualPolygonDrawing()
  return true
}

function setDrawingMode(mode) {
  if (drawingMode.value === mode) {
    if (manualDrawingMode.value === 'polygon') {
      finishManualPolygonDrawing()
    } else {
      cancelDrawing()
    }
    return
  }

  if (drawingManager.value && window.google?.maps?.drawing?.OverlayType) {
    const overlayType = window.google.maps.drawing.OverlayType[mode.toUpperCase()]
    if (!overlayType) return

    drawingMode.value = mode
    drawingManager.value.setDrawingMode(overlayType)
    return
  }

  if (mode === 'polygon') {
    startManualPolygonDrawing()
  }
}

function cancelDrawing(options = {}) {
  if (drawingManager.value) {
    drawingManager.value.setDrawingMode(null)
  }

  if (manualDrawingMode.value === 'polygon') {
    stopManualPolygonDrawing({
      discard: true,
      emitModel: options.emitModel !== false
    })
    return
  }

  drawingMode.value = null
}

function clearAllShapes({ emitModel = true } = {}) {
  const hadShape = hasShape.value
  cancelDrawing({ emitModel: false })

  if (!hadShape && shapes.value.length === 0 && !activeShape.value) {
    return
  }

  const allShapes = new Set(shapes.value)
  if (activeShape.value) {
    allShapes.add(activeShape.value)
  }

  allShapes.forEach(shape => {
    detachShape(shape)
  })

  shapes.value = []
  clearVertexMarkers()
  setActiveShape(null)

  if (emitModel) {
    emit('update:modelValue', [])
    emit('polygon-change', [])
  }
}

function handleEraseAll() {
  if (!hasErasableShape.value) return
  clearAllShapes()
}

function fitMapToPoints(points) {
  if (!map.value || !Array.isArray(points) || points.length === 0) return

  const cleanPoints = normalizePoints(points)
  if (!cleanPoints.length) return

  if (cleanPoints.length === 1) {
    map.value.setCenter(cleanPoints[0])
    return
  }

  const bounds = new window.google.maps.LatLngBounds()
  cleanPoints.forEach(point => bounds.extend(point))
  map.value.fitBounds(bounds, 56)
}

function resizeMap() {
  if (!map.value || !window.google?.maps?.event?.trigger) return

  const center = map.value.getCenter?.() || null
  window.google.maps.event.trigger(map.value, 'resize')

  if (center) {
    map.value.setCenter(center)
  }
}

function drawPolygon(points) {
  if (!map.value) return

  const cleanPoints = normalizePoints(points)
  if (cleanPoints.length < 3) {
    clearAllShapes()
    return
  }

  if (props.singleShape) {
    clearAllShapes({ emitModel: false })
  }

  const polygon = new window.google.maps.Polygon({
    paths: cleanPoints,
    editable: true,
    draggable: true,
    fillColor: '#0d6efd',
    strokeColor: '#0d6efd',
    fillOpacity: 0.25,
    strokeWeight: 3
  })

  polygon.setMap(map.value)
  registerShape(polygon)
  setActiveShape(polygon, 'polygon')

  fitMapToPoints(cleanPoints)
  emitShapeGeometry()
}

function handleOverlayComplete(event) {
  if (!event?.overlay) return

  if (props.singleShape) {
    clearAllShapes({ emitModel: false })
  }

  registerShape(event.overlay)

  if (event.type === 'polygon') {
    setActiveShape(event.overlay, 'polygon')
    emitShapeGeometry()
  } else if (event.type === 'circle') {
    setActiveShape(event.overlay, 'circle')
    emitShapeGeometry()
  }

  cancelDrawing()
}

function initDrawingManager() {
  if (!hasDrawingToolsEnabled()) {
    drawingManager.value = null
    return
  }

  if (!window.google?.maps?.drawing?.DrawingManager) {
    return
  }

  const drawingModes = []
  if (props.enablePolygon) drawingModes.push('polygon')
  if (props.enablePolyline) drawingModes.push('polyline')
  if (props.enableRectangle) drawingModes.push('rectangle')
  if (props.enableCircle) drawingModes.push('circle')
  if (props.enableMarker) drawingModes.push('marker')

  drawingManager.value = new window.google.maps.drawing.DrawingManager({
    drawingControl: false,
    drawingMode: null,
    drawingModes,
    polygonOptions: {
      editable: true,
      draggable: true,
      fillColor: '#0d6efd',
      strokeColor: '#0d6efd',
      fillOpacity: 0.25,
      strokeWeight: 3
    },
    polylineOptions: {
      editable: true,
      strokeColor: '#6610f2',
      strokeWeight: 3
    },
    rectangleOptions: {
      editable: true,
      draggable: true,
      fillColor: '#d63384',
      strokeColor: '#d63384',
      fillOpacity: 0.25,
      strokeWeight: 3
    },
    circleOptions: {
      editable: true,
      draggable: true,
      fillColor: '#fd7e14',
      strokeColor: '#fd7e14',
      fillOpacity: 0.25,
      strokeWeight: 3
    },
    markerOptions: {
      draggable: true
    }
  })

  drawingManager.value.setMap(map.value)
  drawingManager.value.addListener('overlaycomplete', handleOverlayComplete)
}

async function ensureGoogleMaps() {
  if (hasRequiredGoogleMaps()) {
    return
  }

  if (window.google?.maps) {
    await importMissingGoogleMapsLibraries()
    if (hasRequiredGoogleMaps()) {
      return
    }
  }

  if (googleMapsPromise) {
    await googleMapsPromise
    await importMissingGoogleMapsLibraries()
    if (!hasRequiredGoogleMaps()) {
      throw new Error('Google Maps não carregou as bibliotecas necessárias')
    }
    return
  }

  googleMapsPromise = new Promise((resolve, reject) => {
    const callbackName = `initMap_${Date.now()}`

    window[callbackName] = () => {
      resolve()
      delete window[callbackName]
    }

    const script = document.createElement('script')
    script.src = `https://maps.googleapis.com/maps/api/js?key=${GOOGLE_MAPS_KEY}&libraries=geometry&loading=async&callback=${callbackName}`
    script.async = true
    script.defer = true
    script.onerror = () => {
      reject(new Error('Falha ao carregar Google Maps'))
      delete window[callbackName]
    }

    document.head.appendChild(script)
  })

  await googleMapsPromise
  await importMissingGoogleMapsLibraries()

  if (!hasRequiredGoogleMaps()) {
    throw new Error('Google Maps não carregou as bibliotecas necessárias')
  }
}

async function initMap() {
  await ensureGoogleMaps()
  if (!mapElement.value) return

  const initialMapType = normalizeMapType(props.initialMapType)

  map.value = new window.google.maps.Map(mapElement.value, {
    center: props.defaultCenter,
    zoom: props.defaultZoom,
    mapTypeId: initialMapType,
    disableDefaultUI: false,
    mapTypeControl: false
  })

  currentMapType.value = initialMapType
  map.value.addListener('maptypeid_changed', () => {
    currentMapType.value = normalizeMapType(map.value?.getMapTypeId?.())
  })

  initDrawingManager()
  ready.value = true
  emit('map-ready', map.value)

  if (Array.isArray(props.modelValue) && props.modelValue.length >= 3) {
    syncingFromModel.value = true
    drawPolygon(props.modelValue)
    syncingFromModel.value = false
  }
}

watch(
  () => props.modelValue,
  (value) => {
    if (!ready.value || syncingFromModel.value) return

    const incoming = normalizePoints(value)
    const current = getCurrentShapePoints()

    if (pointsAreEqual(incoming, current)) {
      return
    }

    syncingFromModel.value = true
    if (incoming.length >= 3) {
      drawPolygon(incoming)
    } else {
      clearAllShapes()
    }
    syncingFromModel.value = false
  },
  { deep: true }
)

onMounted(() => {
  initMap().catch(() => {
    // Falha de carregamento do SDK não deve quebrar a tela inteira.
  })
})

onBeforeUnmount(() => {
  clearAllShapes({ emitModel: false })
  removeManualMapClickListener()

  if (drawingManager.value) {
    drawingManager.value.setMap(null)
  }
})

defineExpose({
  setDrawingMode,
  cancelDrawing,
  clearAllShapes,
  resize: resizeMap,
  fitToPoints: (points) => fitMapToPoints(points),
  refreshVertexMarkers: () => updateVertexMarkers(),
  getPolygonPoints: () => getCurrentShapePoints()
})
</script>
