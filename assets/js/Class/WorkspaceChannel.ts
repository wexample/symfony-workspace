import App from '@wexample/symfony-loader/js/Class/App';
import EventsService from '@wexample/symfony-loader/js/Services/EventsService';

export const WORKSPACE_SELECT_EVENT = 'workspace:select';

export type WorkspaceSelection = {
  ids: string[];
  // Who chose it: the region that publishes can tell its own choice coming back.
  source: string | null;
};

export type WorkspaceChannelListener = (selection: WorkspaceSelection) => void;

/**
 * What the regions of a workspace agree on: the ids chosen in one of them,
 * which the others show. A channel carries ids, never the records — those come
 * from their repository, where two regions asking for the same one get the
 * same instance.
 *
 * It belongs to no framework: whoever shows the selection listens, a vue
 * through its mixin keeping a copy of its own. Every change is also told to
 * the document, for whoever watches the page as a whole.
 */
export default class WorkspaceChannel {
  public readonly selection: WorkspaceSelection = {
    ids: [],
    source: null,
  };

  private readonly listeners = new Set<WorkspaceChannelListener>();

  constructor(
    public readonly name: string,
    private readonly app: App
  ) {
  }

  get ids(): string[] {
    return this.selection.ids;
  }

  // The one a region showing a single record shows: the first chosen.
  get id(): string | null {
    return this.selection.ids[0] ?? null;
  }

  has(id: string): boolean {
    return this.selection.ids.includes(id);
  }

  select(ids: string | string[], source: string | null = null): void {
    const next = Array.isArray(ids) ? ids : [ids];

    // The same choice again changes nothing, and is not told: a region
    // following the channel would reload what it already shows.
    if (next.length === this.selection.ids.length
      && next.every((id, index) => id === this.selection.ids[index])) {
      return;
    }

    this.selection.ids = [...next];
    this.selection.source = source;

    this.listeners.forEach((listener) => listener(this.selection));

    (this.app.getServiceOrFail(EventsService) as EventsService).trigger(WORKSPACE_SELECT_EVENT, {
      channel: this.name,
      ids: this.selection.ids,
      source,
    });
  }

  clear(source: string | null = null): void {
    this.select([], source);
  }

  listen(listener: WorkspaceChannelListener): void {
    this.listeners.add(listener);
  }

  forget(listener: WorkspaceChannelListener): void {
    this.listeners.delete(listener);
  }
}
