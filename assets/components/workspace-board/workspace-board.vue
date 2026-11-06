<script>
import ZoneTiling from '@wexample/symfony-design-system/components/zone-tiling/zone-tiling.vue';
import AbstractDesignSystemVueMixin from '@wexample/symfony-design-system/js/Vue/AbstractDesignSystemVueMixin';
import { uiStateGet, uiStateSet } from '@wexample/symfony-design-system/js/Helper/UiStateHelper';
import ChoiceList from '@wexample/symfony-design-system/components/choice-list/choice-list.vue';
import ButtonMenu from '@wexample/symfony-design-system/components/button-menu/button-menu.vue';
import { loadIntoTarget, TARGET_PANEL } from '@wexample/symfony-design-system/js/Helper/TargetHelper';

// From how many widgets the choice of one is narrowed by typing.
const PICKER_FILTER_FROM = 8;

// How long the arrangement settles before it is kept: a seam dragged is one
// change, not one per pixel.
const SAVE_DELAY_MS = 600;

// The event a page sends the board to speak to its widgets, and the one it
// hears them on: `detail` is `{ type, detail }`.
export const WORKSPACE_EMIT_EVENT = 'workspace:emit';
export const WORKSPACE_EVENT = 'workspace:event';

const DOMAIN = 'WexampleSymfonyWorkspaceBundle.components.workspace-board.workspace-board::';

// The address parameter naming the one zone a window shows, opened apart.
export const WORKSPACE_ZONE_PARAMETER = 'workspace_zone';

// The address parameters of the page a panel opens on an event no zone
// took: the widget it shows alone, and the event handed to it (`type`,
// `detail` as JSON). Read by the server (WorkspaceExtension), handed back as
// `opened`.
export const WORKSPACE_WIDGET_PARAMETER = 'workspace_widget';
export const WORKSPACE_EVENT_PARAMETER = 'workspace_event';
export const WORKSPACE_DETAIL_PARAMETER = 'workspace_detail';

// The zone of a board opened on an event: none of the arrangement's.
const OPENED_ZONE_ID = 'workspace-opened';

// The entry each zone's bar offers while the board is arranged: its widget
// in a window of its own.
export const WORKSPACE_ACTION_OPEN_WINDOW = 'workspace:open-window';


// A screen the reader composes: zones cut and sized as in a zone-tiling, each
// showing a widget chosen among those the page offers (WorkspaceWidgetInterface
// on the server, handed as `widgets`), the arrangement kept for the account —
// or in the visitor's own state without one. An account keeps several of a
// page, named, chosen and made from the start of the board's bar.
//
// The widgets speak to each other through the board (`workspace.emit(type,
// detail)`, `workspace.on(type, listener)`), and to the rest of the page: each
// event is told on the board's element (`workspace:event`), and the page
// speaks to them by sending it `workspace:emit`. With `live`, what the server
// publishes about the subject reaches them as the `live` event.
//
// A zone — or a split, for a section — may be about a record of its own
// (`subject`); a widget is handed the nearest one, the board's at the top.
//
// Every widget stands in the same frame — a zone that scrolls —, its root a
// `zone--content` of the design system: the room round it the zone's own, or
// none (`zone--content--flush`) for a widget that insets itself — a stage, a
// thread. A zone opens in a window of its own (`workspace_zone` in the
// address): the same page, its widget alone under the board's bar.
//
// A listener returns `true` when it shows what the event names — a slot's
// editor, the slot —; a widget only following it, or pinned to something
// else, returns nothing. An event told with `{ open: true }` that no zone
// takes opens the widget offered that is opened by it (`opens`, WorkspaceWidgetOpeningInterface) in a panel: the same page,
// the widget alone under the board's bar (`opened`), handed the event as its
// zone would have heard it, kept with nothing.
export default {
  template: '#vue-template-wexample-symfony-workspace-bundle-components-workspace-board-workspace-board',

  mixins: [AbstractDesignSystemVueMixin],

  components: {
    ButtonMenu,
    ChoiceList,
    ZoneTiling
  },

  props: {
    layoutKey: {
      type: String,
      required: true
    },
    // { entityName, id }: what the whole board is about.
    subject: {
      type: Object,
      default: null
    },
    // { entityName, id }: what the arrangement is kept under — the subject,
    // unless the page names another: a record's lineage, its versions sharing
    // one arrangement.
    layoutSubject: {
      type: Object,
      default: null
    },
    // [{ name, label, description, icon, view, sizing }]: `sizing`, how a
    // widget sizes its zone itself (WorkspaceWidgetSizingInterface).
    widgets: {
      type: Array,
      default: () => []
    },
    // The arrangement kept, or the page's own; none, a single empty zone.
    layout: {
      type: Object,
      default: null
    },
    // The page's own arrangement, what a new one starts from.
    defaultLayout: {
      type: Object,
      default: null
    },
    saveUrl: {
      type: String,
      default: null
    },
    // The arrangements the account keeps of the page, `[{ id, name }]`, the
    // one shown (`layoutId`, none before the first is kept), and where they
    // are made, chosen and renamed (`{ create, select, rename }`).
    layouts: {
      type: Array,
      default: () => []
    },
    layoutId: {
      type: String,
      default: null
    },
    layoutUrls: {
      type: Object,
      default: null
    },
    saveToken: {
      type: String,
      default: null
    },
    flush: {
      type: Boolean,
      default: false
    },
    live: {
      type: Boolean,
      default: false
    },
    // The bar over the whole board — what all its widgets share, chosen once:
    // `{ view }`. What it holds reaches every widget as its `scope`, and is
    // kept with the arrangement.
    toolbar: {
      type: Object,
      default: null
    },
    // What the page hands every widget besides the subject — the addresses
    // and ids a widget of this page reads —, as the widget's `context`.
    context: {
      type: Object,
      default: () => ({})
    },
    // `(zone, items, board) => items`: the page's own entries in a zone's menu.
    zoneMenu: {
      type: Function,
      default: null
    },
    // `{ widget, type, detail }`: the widget this board shows alone — opened
    // in a panel on an event no zone took —, and that event.
    opened: {
      type: Object,
      default: null
    }
  },

  data() {
    return {
      // What is kept: `{ tree, values }` — the arrangement, and what the bar
      // holds. An arrangement kept before the bar was is the tree alone.
      ...this.readKept(this.saveUrl ? this.layout : uiStateGet(this.app, this.stateKey(), this.layout)),
      // The board being arranged: its zones offer what can be done to them.
      editing: false,
      layoutList: [...this.layouts],
      currentLayoutId: this.layoutId,
      // An arrangement being named in the bar: `{ mode: 'create'|'rename', value }`.
      naming: null,
      // The widgets' vues are loaded with the page, after the board may have
      // drawn: they are looked for once the app says everything is in.
      widgetsReady: false,
      // The zone this window shows alone, opened apart from the board.
      soloZoneId: new URLSearchParams(window.location.search).get(WORKSPACE_ZONE_PARAMETER),
      // The widget this board shows alone, opened on an event: a zone of
      // its own, kept with nothing.
      openedZone: this.opened ? { id: OPENED_ZONE_ID, widget: this.opened.widget, config: {} } : null
    };
  },

  computed: {
    widgetsByName() {
      return Object.fromEntries(this.widgets.map((widget) => [widget.name, widget]));
    },

    // What is done to the arrangements, in the menu beside their field.
    layoutMenuItems() {
      return [
        { value: 'create', label: this.t('layouts.create'), icon: 'ph:bold/plus' },
        { value: 'rename', label: this.t('layouts.rename'), icon: 'ph:bold/pencil-simple' }
      ];
    },

    editLabel() {
      return this.t(this.editing ? 'edit.done' : 'edit.start');
    },

    editIconHtml() {
      return this.renderIcon(this.editing ? 'ph:bold/check' : 'ph:bold/layout');
    },

    // The widgets as the choice list reads them, by their name.
    pickerChoices() {
      return this.widgets.map((widget) => ({
        value: widget.name,
        label: widget.label,
        icon: widget.icon,
        description: widget.description
      }));
    },

    pickerFilterFrom() {
      return PICKER_FILTER_FROM;
    },

    // The zone shown alone, while it still is one of the board's; gone from
    // it, the board whole.
    soloZone() {
      if (this.openedZone) {
        return this.openedZone;
      }

      return this.soloZoneId && this.tree ? this.findZone(this.tree, this.soloZoneId) : null;
    },

    // This page without the zone it shows alone.
    boardUrl() {
      const url = new URL(window.location.href);
      [WORKSPACE_ZONE_PARAMETER, WORKSPACE_WIDGET_PARAMETER, WORKSPACE_EVENT_PARAMETER, WORKSPACE_DETAIL_PARAMETER]
        .forEach((parameter) => url.searchParams.delete(parameter));

      return url.toString();
    },

    // Each zone's subject, the nearest one above it holding.
    subjects() {
      const subjects = {};
      const walk = (node, inherited) => {
        const subject = node.subject ?? inherited;

        subjects[node.id] = subject;
        (node.children ?? []).forEach((child) => walk(child, subject));
      };

      if (this.tree) {
        walk(this.tree, this.subject);
      }

      return subjects;
    }
  },

  created() {
    this.listeners = new Map();
    // The event this board was opened on, for the first to listen for it.
    this.openedEvent = this.opened?.type ? { type: this.opened.type, detail: this.opened.detail } : null;
    this.definitions = {};
    this.saveTimer = null;
    // What a widget is handed to speak through: the board's words, not the
    // board itself.
    this.api = Object.freeze({
      layoutKey: this.layoutKey,
      emit: (type, detail, options) => this.emit(type, detail, options),
      on: (type, listener) => this.on(type, listener),
      updateConfig: (zoneId, config) => this.updateZone(zoneId, { config }),
      // What the bar holds, kept with the arrangement.
      updateValues: (values) => this.onValuesChange(values)
    });
  },

  mounted() {
    this.runWhenAppReady(() => {
      this.widgetsReady = true;
    });
    this.$el.addEventListener(WORKSPACE_EMIT_EVENT, this.onPageEmit);

    if (this.live && this.subject) {
      this.app.services.liveUpdates?.connectToEntity({
        entityName: this.subject.entityName,
        id: this.subject.id,
        owner: this,
        onMessage: (connection, payload) => this.emit('live', payload)
      });
    }
  },

  beforeUnmount() {
    this.$el.removeEventListener(WORKSPACE_EMIT_EVENT, this.onPageEmit);
    this.app.services.liveUpdates?.disconnectOwner(this);
    window.clearTimeout(this.saveTimer);
  },

  methods: {
    t(key, args) {
      return this.trans(`${DOMAIN}${key}`, args);
    },

    renderIcon(name) {
      return name ? this.app.getServiceOrFail('icon').icon(name) : '';
    },

    stateKey() {
      const kept = this.layoutSubject ?? this.subject;

      return ['ui.workspace.layout', this.layoutKey, kept?.entityName, kept?.id]
        .filter((part) => part !== undefined && part !== null)
        .join('.');
    },

    // The vue of a widget, as the loader registered it: the page required it.
    // Asked before its script is in, it is not there yet, and asked again
    // once the app is ready.
    widgetComponent(name) {
      return this.vueComponent(this.widgetsByName[name]);
    },

    toolbarComponent() {
      return this.vueComponent(this.toolbar);
    },

    // `view` resolved by the server as the loader registers it.
    vueComponent(item) {
      if (!item || !this.widgetsReady) {
        return null;
      }

      const { view } = item;

      if (!this.definitions[view] && this.app.getBundleClassDefinition(`vue:${view}`)) {
        this.definitions[view] = this.app
          .getServiceOrFail('vue')
          .initComponent(view, this.rootComponent);
      }

      return this.definitions[view] ?? null;
    },

    // A zone showing a widget offered: drawn, or about to be.
    showsWidget(zone) {
      return Boolean(zone.widget && this.widgetsByName[zone.widget]);
    },

    // What a zone is called while the board is arranged: its widget's name.
    zoneLabel(zone) {
      return this.widgetsByName[zone.widget]?.label ?? null;
    },

    // How the widget a zone shows sizes it, if it does: `{ height: 'content' }`.
    // Not while the board is arranged: the zone shows the widgets to choose
    // from, not its widget, and is sized by hand like any other.
    zoneSizing(zone) {
      return this.editing ? null : this.widgetsByName[zone.widget]?.sizing ?? null;
    },

    // A widget taken for a zone, by its name.
    pick(zone, name) {
      if (name && name !== zone.widget) {
        this.updateZone(zone.id, { widget: name, config: {} });
      }
    },

    // A zone's bar while the board is arranged: its widget opened apart
    // first, once it has one, then what the tiling and the page offer.
    zoneMenuItems(zone, items) {
      const own = this.showsWidget(zone) ? [{
        value: WORKSPACE_ACTION_OPEN_WINDOW,
        icon: 'ph:bold/arrow-square-out',
        label: this.t('zone.open_window'),
        run: () => window.open(this.zoneUrl(zone), '_blank', 'noopener')
      }, { separator: true }] : [];
      const all = [...own, ...items];

      return this.zoneMenu ? this.zoneMenu(zone, all, this.api) : all;
    },

    zoneUrl(zone) {
      const url = new URL(window.location.href);
      url.searchParams.set(WORKSPACE_ZONE_PARAMETER, zone.id);

      return url.toString();
    },

    findZone(node, id) {
      if (node.id === id) {
        return node;
      }

      for (const child of node.children ?? []) {
        const found = this.findZone(child, id);

        if (found) {
          return found;
        }
      }

      return null;
    },

    // What a zone carries changed: through the tiling, or — a zone shown
    // alone, with no tiling — in the tree itself.
    updateZone(zoneId, patch) {
      if (zoneId === this.openedZone?.id) {
        this.openedZone = { ...this.openedZone, ...patch };

        return;
      }

      if (this.$refs.tiling) {
        this.$refs.tiling.update(zoneId, patch);

        return;
      }

      const tree = JSON.parse(JSON.stringify(this.tree));
      const zone = this.findZone(tree, zoneId);

      if (zone) {
        Object.assign(zone, patch);
        this.onTreeChange(tree);
      }
    },

    readKept(kept) {
      return kept && 'tree' in kept
        ? { tree: kept.tree, values: kept.values ?? {} }
        : { tree: kept, values: {} };
    },

    onTreeChange(tree) {
      this.tree = tree;
      this.scheduleSave();
      this.emit('layout', tree);
    },

    onValuesChange(values) {
      this.values = values;
      this.scheduleSave();
      this.emit('values', values);
    },

    scheduleSave() {
      window.clearTimeout(this.saveTimer);
      this.saveTimer = window.setTimeout(() => {
        this.saveTimer = null;
        this.save();
      }, SAVE_DELAY_MS);
    },

    // Kept for the account where there is one, in the visitor's state
    // otherwise. A failed save loses nothing on screen.
    async save() {
      if (!this.saveUrl) {
        uiStateSet(this.app, this.stateKey(), { tree: this.tree, values: this.values });

        return;
      }

      try {
        const layout = await this.postLayout(this.saveUrl, {
          ...this.layoutScope(),
          id: this.currentLayoutId,
          tree: { tree: this.tree, values: this.values }
        });

        this.knowLayout(layout);
      } catch {
        // Kept on screen; the next change tries again.
      }
    },

    // What is kept now, rather than once it settles: before another
    // arrangement is shown.
    async saveNow() {
      if (this.saveTimer) {
        window.clearTimeout(this.saveTimer);
        this.saveTimer = null;
        await this.save();
      }
    },

    layoutScope() {
      const kept = this.layoutSubject ?? this.subject;

      return {
        layoutKey: this.layoutKey,
        subjectType: kept?.entityName ?? null,
        subjectId: kept?.id ?? null
      };
    },

    async postLayout(url, payload) {
      const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.saveToken ?? '' },
        body: JSON.stringify(payload)
      });

      if (!response.ok) {
        throw new Error(`The arrangement could not be kept (${response.status}).`);
      }

      return response.json();
    },

    // An arrangement the server answered with: the one shown, listed.
    knowLayout(layout) {
      if (!layout?.id) {
        return;
      }

      this.currentLayoutId = layout.id;
      this.layoutList = this.layoutList.some((item) => item.id === layout.id)
        ? this.layoutList.map((item) => (item.id === layout.id ? { id: layout.id, name: layout.name } : item))
        : [...this.layoutList, { id: layout.id, name: layout.name }];
    },

    // Its tree and what its bar holds drawn, as when the page opens on it.
    showLayout(layout) {
      const kept = this.readKept(layout.tree);

      this.knowLayout(layout);
      this.tree = kept.tree;
      this.values = kept.values;
      this.emit('layout', this.tree);
      this.emit('values', this.values);
    },

    layoutLabel(layout) {
      return layout.name || this.t('layouts.unnamed');
    },

    async selectLayout(id) {
      if (!id || id === this.currentLayoutId) {
        return;
      }

      await this.saveNow();
      this.showLayout(await this.postLayout(this.layoutUrls.select, { id }));
    },

    onLayoutMenu(item) {
      const current = this.layoutList.find((layout) => layout.id === this.currentLayoutId);

      this.naming = { mode: item.value, value: item.value === 'rename' ? (current?.name ?? '') : '' };
      this.$nextTick(() => this.$refs.namingInput?.focus());
    },

    // A new arrangement, from the page's own, or the one shown renamed —
    // kept first when it never was.
    async submitNaming() {
      const { mode, value } = this.naming;

      this.naming = null;

      if (mode === 'create') {
        await this.saveNow();
        this.showLayout(await this.postLayout(this.layoutUrls.create, {
          ...this.layoutScope(),
          name: value,
          tree: { tree: this.defaultLayout ?? this.tree, values: this.values }
        }));

        return;
      }

      if (!this.currentLayoutId) {
        window.clearTimeout(this.saveTimer);
        await this.save();
      }

      this.knowLayout(await this.postLayout(this.layoutUrls.rename, { id: this.currentLayoutId, name: value }));
    },

    // Told to the widgets listening, then to the page; taken by none of
    // them and told to `open`, the widget opened by it in a panel. Whether a
    // widget took it.
    emit(type, detail, { open = false } = {}) {
      const taken = this.tell(type, detail);

      this.$el?.dispatchEvent(new CustomEvent(WORKSPACE_EVENT, {
        bubbles: true,
        detail: { layoutKey: this.layoutKey, type, detail }
      }));

      if (open && !taken) {
        this.openPanel(type, detail);
      }

      return taken;
    },

    // Told to the widgets listening: taken when one of them shows it (`true`).
    tell(type, detail) {
      let taken = false;

      (this.listeners.get(type) ?? []).forEach((listener) => {
        if (listener(detail) === true) {
          taken = true;
        }
      });

      return taken;
    },

    // Returns what stops listening; a widget calls it as it goes. The event
    // this board was opened on reaches the first to listen for it.
    on(type, listener) {
      if (!this.listeners.has(type)) {
        this.listeners.set(type, new Set());
      }

      this.listeners.get(type).add(listener);

      if (this.openedEvent?.type === type) {
        const { detail } = this.openedEvent;

        this.openedEvent = null;
        queueMicrotask(() => listener(detail));
      }

      return () => this.listeners.get(type)?.delete(listener);
    },

    // The widget offered that is opened by the event, alone in a panel —
    // this page, asked to draw it alone —; none, nothing.
    openPanel(type, detail) {
      const widget = this.widgets.find((item) => (item.opens ?? []).includes(type));

      if (!widget) {
        return;
      }

      const url = new URL(this.boardUrl);
      url.searchParams.set(WORKSPACE_WIDGET_PARAMETER, widget.name);
      url.searchParams.set(WORKSPACE_EVENT_PARAMETER, type);
      url.searchParams.set(WORKSPACE_DETAIL_PARAMETER, JSON.stringify(detail ?? null));

      loadIntoTarget(this.app, TARGET_PANEL, url.toString());
    },

    onPageEmit(event) {
      if (event.detail?.type) {
        // Not told back to the page: it is the page speaking.
        (this.listeners.get(event.detail.type) ?? []).forEach((listener) => listener(event.detail.detail));
      }
    }
  }
};
</script>
