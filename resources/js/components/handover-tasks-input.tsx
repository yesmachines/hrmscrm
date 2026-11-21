import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { parseHandoverTasks, serializeHandoverTasks } from '@/lib/handover-tasks';

type Props = {
    idPrefix: string;
    value: string;
    onChange: (value: string) => void;
};

export default function HandoverTasksInput({
    idPrefix,
    value,
    onChange,
}: Props) {
    const tasks = parseHandoverTasks(value);

    return (
        <div className="space-y-2">
            {tasks.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    No tasks added yet.
                </p>
            )}
            {tasks.map((task, index) => (
                <div key={index} className="flex items-center gap-2">
                    <Input
                        id={`${idPrefix}-${index + 1}`}
                        aria-label={`Handover task ${index + 1}`}
                        value={task}
                        placeholder="Describe a task or responsibility..."
                        onChange={(event) =>
                            onChange(
                                serializeHandoverTasks(
                                    tasks.map((currentTask, taskIndex) =>
                                        taskIndex === index
                                            ? event.target.value
                                            : currentTask,
                                    ),
                                ),
                            )
                        }
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="shrink-0 text-destructive"
                        aria-label={`Remove handover task ${index + 1}`}
                        onClick={() =>
                            onChange(
                                serializeHandoverTasks(
                                    tasks.filter(
                                        (_, taskIndex) => taskIndex !== index,
                                    ),
                                ),
                            )
                        }
                    >
                        <Trash2 />
                    </Button>
                </div>
            ))}
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() =>
                    onChange(serializeHandoverTasks([...tasks, '']))
                }
            >
                <Plus />
                Add task
            </Button>
        </div>
    );
}
