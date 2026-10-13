import Component from '@wexample/symfony-loader/js/Class/Component';
import EmbedService from '@wexample/symfony-loader/js/Services/EmbedService';
import WorkspaceService from '../../js/Services/WorkspaceService';
import WorkspaceChannel, { WorkspaceSelection } from '../../js/Class/WorkspaceChannel';

// Options: `channel`, the name it follows; `embed`, the name of the embed it
// fills; `path`, the page to load, `{id}` standing for the id chosen;
// `current`, the id the embed opens on, not loaded a second time.
export default class extends Component {
  private channel?: WorkspaceChannel;
  private shown?: string;

  protected async activateListeners(): Promise<void> {
    await super.activateListeners();

    this.shown = this.options.current;
    this.channel = (this.app.getServiceOrFail(WorkspaceService) as WorkspaceService).channel(this.options.channel);
    this.channel.listen(this.onSelect);
    // A choice made before it was there — the rail opening on its first.
    this.onSelect(this.channel.selection);
  }

  protected async deactivateListeners(): Promise<void> {
    this.channel?.forget(this.onSelect);

    await super.deactivateListeners();
  }

  // Nothing chosen leaves the embed as it is: emptying it would be a choice
  // of its own, the page's to make.
  private onSelect = (selection: WorkspaceSelection): void => {
    const id = selection.ids[0];

    if (!id || id === this.shown) {
      return;
    }

    this.shown = id;

    (this.app.getServiceOrFail(EmbedService) as EmbedService).load(
      this.options.embed,
      (this.options.path as string).replace('{id}', encodeURIComponent(id))
    );
  };
}
