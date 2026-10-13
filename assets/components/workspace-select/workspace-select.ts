import Component from '@wexample/symfony-loader/js/Class/Component';
import WorkspaceService from '../../js/Services/WorkspaceService';
import WorkspaceChannel, { WorkspaceSelection } from '../../js/Class/WorkspaceChannel';

// Options: `channel`, the name it publishes on; `source`, the name it signs
// its choices with.
export default class extends Component {
  private channel?: WorkspaceChannel;
  // The element it stands in, where the choices are.
  private scope?: HTMLElement;

  protected async activateListeners(): Promise<void> {
    await super.activateListeners();

    this.channel = (this.app.getServiceOrFail(WorkspaceService) as WorkspaceService).channel(this.options.channel);
    this.scope = this.el.parentElement;
    this.channel.listen(this.onSelect);
    this.scope.addEventListener('click', this.onClick);
    this.onSelect(this.channel.selection);
  }

  protected async deactivateListeners(): Promise<void> {
    this.scope?.removeEventListener('click', this.onClick);
    this.channel?.forget(this.onSelect);

    await super.deactivateListeners();
  }

  private onClick = (event: MouseEvent): void => {
    const item = (event.target as HTMLElement).closest<HTMLElement>('[data-workspace-select]');

    if (!item || !this.scope.contains(item)) {
      return;
    }

    event.preventDefault();
    this.channel.select(item.dataset.workspaceSelect, this.options.source ?? null);
  };

  // Marked from the channel rather than from the click: a choice made in
  // another region shows here too.
  private onSelect = (selection: WorkspaceSelection): void => {
    this.scope?.querySelectorAll<HTMLElement>('[data-workspace-select]').forEach((item) => {
      if (selection.ids.includes(item.dataset.workspaceSelect)) {
        item.setAttribute('aria-current', 'true');
      } else {
        item.removeAttribute('aria-current');
      }
    });
  };
}
