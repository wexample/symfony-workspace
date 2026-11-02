<script>
import ZoneTiling from '@wexample/symfony-design-system/components/zone-tiling/zone-tiling.vue';
import AbstractDesignSystemVueMixin from '@wexample/symfony-design-system/js/Vue/AbstractDesignSystemVueMixin';
import { uiStateGet, uiStateSet } from '@wexample/symfony-design-system/js/Helper/UiStateHelper';
import { filterTextMatches } from '@wexample/symfony-design-system/js/Helper/FilterHelper';

// What a zone's menu adds once it shows a widget.
export const WORKSPACE_ACTION_CHANGE = 'workspace:change';

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


// A screen the reader composes: zones cut and sized as in a zone-tiling, each
// showing a widget chosen among those the page offers (WorkspaceWidgetInterface
// on the server, handed as `widgets`), the arrangement kept for the account —
// or in the visitor's own state without one.
//
// The widgets speak to each other through the board (`workspace.emit(type,
// detail)`, `workspace.on(type, listener)`), and to the rest of the page: each
// event is told on the board's element (`workspace:event`), and the page
// speaks to them by sending it `workspace:emit`. With `live`, what the server
// publishes about the subject reaches them as the `live` event.
//
// A zone — or a split, for a section — may be about a record of its own
// (`subject`); a widget is handed the nearest one, the board's at the top.
export default {
  template: '#vue-template-wexample-symfony-workspace-bundle-components-workspace-board-workspace-board',

  mixins: [AbstractDesignSystemVueMixin],

  components: {
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
    // [{ name, label, description, icon, view }].
    widgets: {
      type: Array,
      default: () => []
    },
    // The arrangement kept, or the page's own; none, a single empty zone.
    layout: {
      type: Object,
      default: null
    },
    saveUrl: {
      type: String,
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
    }
  },

  data() {
    return {
      // What is kept: `{ tree, values }` — the arrangement, and what the bar
      // holds. An arrangement kept before the bar was is the tree alone.
      ...this.readKept(this.saveUrl ? this.layout : uiStateGet(this.app, this.stateKey(), this.layout)),
      pickerFilter: '',
      // The board being arranged: its zones offer what can be done to them.
      editing: false,
      // The widgets' vues are loaded with the page, after the board may have
      // drawn: they are looked for once the app says everything is in.
      widgetsReady: false
    };
  },

  computed: {
    widgetsByName() {
      return Object.fromEntries(this.widgets.map((widget) => [widget.name, widget]));
    },

    editLabel() {
      return this.t(this.editing ? 'edit.done' : 'edit.start');
    },

    editIconHtml() {
      return this.renderIcon(this.editing ? 'ph:bold/check' : 'ph:bold/layout');
    },

    pickerFilterable() {
      return this.widgets.length >= PICKER_FILTER_FROM;
    },

    pickerWidgets() {
      return this.widgets.filter((widget) => filterTextMatches(this.pickerFilter, widget.label, widget.description));
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
    this.definitions = {};
    this.saveTimer = null;
    // What a widget is handed to speak through: the board's words, not the
    // board itself.
    this.api = Object.freeze({
      layoutKey: this.layoutKey,
      emit: (type, detail) => this.emit(type, detail),
      on: (type, listener) => this.on(type, listener),
      updateConfig: (zoneId, config) => this.$refs.tiling?.update(zoneId, { config }),
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
      return ['ui.workspace.layout', this.layoutKey, this.subject?.entityName, this.subject?.id]
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
      return this.widgetsByName[zone.widget]?.label ?? this.t('picker.title');
    },

    pick(zone, widget) {
      this.pickerFilter = '';
      this.$refs.tiling.update(zone.id, { widget: widget.name, config: {} });
    },

    zoneMenuItems(zone, items) {
      const own = zone.widget
        ? [
          {
            value: WORKSPACE_ACTION_CHANGE,
            icon: 'ph:bold/swap',
            label: this.t('menu.change'),
            run: (target, tiling) => tiling.update(target.id, { widget: null, config: {} })
          },
          { separator: true }
        ]
        : [];
      const all = [...own, ...items];

      return this.zoneMenu ? this.zoneMenu(zone, all, this.api) : all;
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
      this.saveTimer = window.setTimeout(() => this.save(), SAVE_DELAY_MS);
    },

    // Kept for the account where there is one, in the visitor's state
    // otherwise. A failed save loses nothing on screen.
    async save() {
      if (!this.saveUrl) {
        uiStateSet(this.app, this.stateKey(), { tree: this.tree, values: this.values });

        return;
      }

      try {
        await fetch(this.saveUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.saveToken ?? '' },
          body: JSON.stringify({
            layoutKey: this.layoutKey,
            subjectType: this.subject?.entityName ?? null,
            subjectId: this.subject?.id ?? null,
            tree: { tree: this.tree, values: this.values }
          })
        });
      } catch {
        // Kept on screen; the next change tries again.
      }
    },

    // Told to the widgets listening, then to the page.
    emit(type, detail) {
      (this.listeners.get(type) ?? []).forEach((listener) => listener(detail));
      this.$el?.dispatchEvent(new CustomEvent(WORKSPACE_EVENT, {
        bubbles: true,
        detail: { layoutKey: this.layoutKey, type, detail }
      }));
    },

    // Returns what stops listening; a widget calls it as it goes.
    on(type, listener) {
      if (!this.listeners.has(type)) {
        this.listeners.set(type, new Set());
      }

      this.listeners.get(type).add(listener);

      return () => this.listeners.get(type)?.delete(listener);
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
