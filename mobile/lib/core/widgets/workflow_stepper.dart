import 'package:flutter/material.dart';

import '../../core/widgets/status_badge.dart';

class WorkflowStepper extends StatelessWidget {
  const WorkflowStepper({
    super.key,
    required this.steps,
    required this.currentStatus,
  });

  final List<String> steps;
  final String currentStatus;

  @override
  Widget build(BuildContext context) {
    final current = steps.indexOf(currentStatus);

    return SizedBox(
      height: 72,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: steps.length,
        separatorBuilder: (context, index) => Container(
          width: 16,
          alignment: Alignment.center,
          child: Container(height: 2, color: Theme.of(context).dividerColor),
        ),
        itemBuilder: (context, index) {
          final step = steps[index];
          final done = index <= current;

          return Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              done
                  ? const Icon(
                      Icons.check_circle,
                      size: 22,
                      color: Color(0xFF059669),
                    )
                  : const Icon(
                      Icons.circle_outlined,
                      size: 22,
                      color: Color(0xFF94A3B8),
                    ),
              const SizedBox(height: 4),
              StatusBadge(status: step, label: step.toUpperCase()),
            ],
          );
        },
      ),
    );
  }
}
