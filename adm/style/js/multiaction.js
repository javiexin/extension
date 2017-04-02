/**
* Unmark/mark checkboxes for an specific action
* action = action [enable/disable/delete_data/false to uncheck]
*/
function markExtList(action)
{
	var parent = document.getElementById('extension_multi_action');
	if (!parent)
	{
		return;
	}

	var boxes = parent.querySelectorAll('input[ext-actions]');

	for (var x = 0; x < boxes.length; x++)
	{
		boxes[x].checked = false;
		if (boxes[x].getAttribute('ext-actions').indexOf(action) != -1)
		{
			boxes[x].checked = true;
		}
	}

	validButtons(parent);
}

/**
* Mark buttons as valid (enabled) or invalid (disabled) depending on the selected set of extensions
* obj = changed element
*/
function validButtons(obj)
{
	var parent = document.getElementById('extension_multi_action');
	if (!parent)
	{
		return;
	}

	var buttons = parent.querySelectorAll('button[type=submit][name=action][id]');
	var boxes = parent.querySelectorAll('input[ext-actions]');

	for (var b = 0; b < buttons.length; b++)
	{
		if (buttons[b].id.substr(0, 7) == 'button_')
		{
			var action = buttons[b].id.substr(7);
			buttons[b].disabled = true;

			for (var x = 0; x < boxes.length; x++)
			{
				if (boxes[x].checked && (boxes[x].getAttribute('ext-actions').split(' ').indexOf(action) != -1))
				{
					 buttons[b].disabled = false;
					 break;
				}
			}
		}
	}
}

