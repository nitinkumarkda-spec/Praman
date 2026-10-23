// PRAMAN — Onboarding / Terms Splash Screen with Accept & Skip options.
import 'package:flutter/material.dart';

import '../app/fa.dart';
import '../app/store.dart';
import '../app/theme.dart';
import 'common.dart';

class OnboardingSplashScreen extends StatelessWidget {
  final VoidCallback onAccept;
  final VoidCallback onSkip;

  const OnboardingSplashScreen({
    super.key,
    required this.onAccept,
    required this.onSkip,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: C.bg,
      body: SafeArea(
        child: Column(
          children: [
            // Top Bar with Skip shortcut
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: C.navy100,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: C.borderStrong),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(FA.shieldHalved, size: 12, color: C.navy700),
                        const SizedBox(width: 6),
                        Text(
                          'KDA OFFICIAL APP',
                          style: T.s(11, weight: FontWeight.w700, color: C.navy800, family: T.poppins),
                        ),
                      ],
                    ),
                  ),
                  TextButton.icon(
                    onPressed: onSkip,
                    icon: const Icon(FA.arrowRight, size: 12, color: C.muted),
                    label: Text(
                      'Skip',
                      style: T.s(13, weight: FontWeight.w600, color: C.muted),
                    ),
                  ),
                ],
              ),
            ),

            // Scrollable Content
            Expanded(
              child: SingleChildScrollView(
                physics: const BouncingScrollPhysics(),
                padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    const SizedBox(height: 10),

                    // KDA Emblem & App Identity
                    const Emblem(size: 84),
                    const SizedBox(height: 16),
                    Text(
                      App.name,
                      style: T.s(28, weight: FontWeight.w800, color: C.navy900, spacing: 4, family: T.poppins),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      App.org,
                      style: T.s(14, weight: FontWeight.w600, color: C.gold600, family: T.poppins),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Government of Rajasthan • राजस्थान सरकार',
                      style: T.s(12, weight: FontWeight.w500, color: C.muted),
                    ),

                    const SizedBox(height: 24),

                    // Main Terms Card
                    Container(
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: C.border),
                        boxShadow: const [
                          BoxShadow(color: Color(0x0C0F2B52), blurRadius: 18, offset: Offset(0, 8)),
                        ],
                      ),
                      padding: const EdgeInsets.all(20),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Container(
                                width: 36,
                                height: 36,
                                decoration: BoxDecoration(
                                  color: C.navy50,
                                  borderRadius: BorderRadius.circular(8),
                                  border: Border.all(color: C.navy100),
                                ),
                                child: const Icon(FA.fileContract, size: 16, color: C.navy700),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'Terms & Access Policy',
                                      style: T.s(16, weight: FontWeight.w700, color: C.navy900),
                                    ),
                                    Text(
                                      'उपयोग की शर्तें एवं आधिकारिक सहमति',
                                      style: T.s(11.5, weight: FontWeight.w500, color: C.muted),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),

                          const SizedBox(height: 16),
                          const Divider(height: 1, color: C.border),
                          const SizedBox(height: 16),

                          // Bullet point 1
                          _policyItem(
                            icon: FA.buildingColumns,
                            iconColor: C.navy700,
                            title: 'Official Property Records Access',
                            subtitle:
                                'Access verified land records, title tracking, auction-readiness assessment and multi-section NOC details directly from KDA.',
                          ),
                          const SizedBox(height: 14),

                          // Bullet point 2
                          _policyItem(
                            icon: FA.shieldHalved,
                            iconColor: C.green600,
                            title: 'Authorized Government Governance',
                            subtitle:
                                'All assessments and role actions are logged and protected under Kota Development Authority compliance guidelines.',
                          ),
                          const SizedBox(height: 14),

                          // Bullet point 3
                          _policyItem(
                            icon: FA.circleCheck,
                            iconColor: C.gold600,
                            title: 'Secure Offline Field Sync',
                            subtitle:
                                'Audit logs and property inspection data are cached securely on your device for field visits and sync automatically.',
                          ),

                          const SizedBox(height: 16),

                          // Disclaimer Note Box
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: C.gold100.withAlpha(128),
                              borderRadius: BorderRadius.circular(10),
                              border: Border.all(color: C.gold400.withAlpha(150)),
                            ),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Icon(FA.circleInfo, size: 14, color: C.gold600),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Text(
                                    'By pressing "Accept & Open", you acknowledge and agree to comply with KDA Property Assessment Network access rules.',
                                    style: T.s(11.5, color: C.navy900, height: 1.35),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 20),
                  ],
                ),
              ),
            ),

            // Bottom Sticky Action Bar (Skip & Accept Buttons)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
              decoration: const BoxDecoration(
                color: Colors.white,
                border: Border(top: BorderSide(color: C.border)),
                boxShadow: [
                  BoxShadow(color: Color(0x0A000000), blurRadius: 10, offset: Offset(0, -3)),
                ],
              ),
              child: Row(
                children: [
                  // Skip Button
                  Expanded(
                    flex: 2,
                    child: SizedBox(
                      height: 48,
                      child: OutlinedButton(
                        onPressed: onSkip,
                        style: OutlinedButton.styleFrom(
                          foregroundColor: C.navy800,
                          side: const BorderSide(color: C.navy600, width: 1.2),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                        child: Text(
                          'Skip',
                          style: T.s(14, weight: FontWeight.w600, color: C.navy800),
                        ),
                      ),
                    ),
                  ),

                  const SizedBox(width: 14),

                  // Accept & Open Button
                  Expanded(
                    flex: 3,
                    child: SizedBox(
                      height: 48,
                      child: ElevatedButton.icon(
                        onPressed: onAccept,
                        icon: const Icon(FA.circleCheck, size: 16, color: Colors.white),
                        label: Text(
                          'Accept & Open',
                          style: T.s(14, weight: FontWeight.w700, color: Colors.white),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: C.navy800,
                          elevation: 2,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _policyItem({
    required IconData icon,
    required Color iconColor,
    required String title,
    required String subtitle,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          margin: const EdgeInsets.only(top: 2),
          width: 28,
          height: 28,
          decoration: BoxDecoration(
            color: iconColor.withAlpha(25),
            shape: BoxShape.circle,
          ),
          child: Icon(icon, size: 13, color: iconColor),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: T.s(13.5, weight: FontWeight.w600, color: C.navy900)),
              const SizedBox(height: 3),
              Text(subtitle, style: T.s(12, color: C.muted, height: 1.35)),
            ],
          ),
        ),
      ],
    );
  }
}

